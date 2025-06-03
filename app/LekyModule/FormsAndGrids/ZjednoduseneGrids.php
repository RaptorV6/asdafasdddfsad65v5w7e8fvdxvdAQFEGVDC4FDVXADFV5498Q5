<?php
namespace App\LekyModule\Grids;

use Ublaboo\DataGrid\AkesoGrid;
use \Nette\Utils\Html;
use \Nette\Forms\Container;
use Ublaboo\DataGrid\DataGrid;

class ZjednoduseneGridFactory extends \App\Factory\BaseDataGridFactory {

    public function __construct(\Nette\Security\User $user, \Dibi\Connection $db, array $parameters) {
        //parent::__construct($user, $db, $parameters);
    }

    public function setZjednoduseneGrid(AkesoGrid $grid, $prava, $poj, $defaultHodnoty): AkesoGrid {
        $grid->setPrimaryKey("ID_LEKY");
        $grid->setStrictSessionFilterValues(false);
        $grid->setColumnsHideable();
        
        $grid->setDefaultSort(array('ID_LEKY' => 'DESC'));

        $grid->addColumnText('ORGANIZACE', 'Organizace')
             ->setSortable()
             ->setDefaultHide()  
             ->setFilterMultiSelect(\App\LekyModule\Presenters\ZjednodusenePresenter::ORGANIZACE)
             ->addAttribute('class', 'multiselect');

        $grid->addColumnText('NAZ', 'Název')
             ->setSortable()
             ->setRenderer(function ($item) {
                 return $item->NAZ;
             })
             ->setFilterText()
             ->setSplitWordsSearch(true);

        $grid->addColumnText('POZNAMKA', 'Poznámka pro všechny ZP')
             ->setSortable()
             ->setFilterText();

        $grid->addColumnText('UCINNA_LATKA', 'Učinná látka')
             ->setSortable()
             ->setFilterText();

        $grid->addColumnText('BIOSIMOLAR', 'Biosimilar')
             ->setSortable()
             ->setFilterText();

        $grid->addColumnText('ATC', 'ATC')
             ->setSortable()
             ->setReplacement(["" => "-"])
             ->setFilterText()
             ->setSplitWordsSearch(true);

        // ✅ STAVY POJIŠŤOVEN
        $pojistovny = ['111', '201', '205', '207', '209', '211', '213'];

        foreach ($pojistovny as $value) {
            $grid->addColumnText($value . '_STAV', $value . ' Stav')
                 ->setSortable()
                 ->setRenderer(function ($item) use ($value) {
                     $el = Html::el('div');
                     $el->class($item["poj" . $value . "_BARVA"]);
                     if ($item[$value . "_STAV"] == 'Nasmlouváno') {
                         $item[$value . "_NASMLOUVANO_OD"] = $item[$value . "_NASMLOUVANO_OD"] ?? "Nezadáno";
                         $item[$value . "_STAV"] = $item[$value . "_STAV"] . ' ' . $item[$value . "_NASMLOUVANO_OD"];
                     } else {
                         $item[$value . "_STAV"] = $item[$value . "_STAV"] ?? "Nezadáno";
                     }
                     if (!empty($item[$value . '_POZNAMKA'])) {
                         $el->title($item[$value . '_POZNAMKA']);
                         echo $el->addHtml(Html::el('img')->width('12%')->src('/Lekovnice/www/img/mark.png')->style('float:left;'));
                     }
                     $el->setText($item[$value . "_STAV"]);
                     return $el;
                 })
                 ->setFilterText();
        }

        // ✅ DETAIL - používá negroupovaná data
        $grid->setItemsDetail(true, "ID_LEKY")
             ->setClass("btn btn-primary btn-sm ajax")
             ->setTitle("Diagnostické skupiny")
             ->setText("Detail (Info)")
             ->setIcon("arrow-down")
             ->setTemplateParameters(["ID_LEKY"=>"ID_LEKY"])
             ->setType("template")
             ->setTemplate(__DIR__."/../templates/Zjednodusene/itemDetail.latte");

        // ✅ HROMADNÉ AKCE
        if (($prava == '9' || $prava == '2') && $poj == 1) {
            $grid->addGroupButtonAction('Hromadná změna pojišťoven')
                 ->setClass("btn btn-success")
                 ->setAttribute("style", "float:initial !important;")
                 ->onClick[] = function ($id) use ($grid) {
                     $grid->presenter->redirect(':Leky:Zjednodusene:Hromad', json_encode($id));
                 };
            $grid->addGroupButtonAction('Hromadná změna dg skupiny')
                 ->setClass("btn btn-primary")
                 ->setAttribute("style", "float:initial !important;")
                 ->onClick[] = function ($id) use ($grid) {
                     $grid->presenter->redirect(':Leky:Zjednodusene:Hromadiag', json_encode($id));
                 };
        } else {
            $grid->addGroupButtonAction("")
                 ->setAttribute("style", "display:none;");
        }

        $container = $grid->getComponent("filter-group_action");
        
        $container->addCheckbox("histori", "Akord léky: ")
                  ->setDefaultValue($grid->getSessionData("histori"))
                  ->setHtmlId('histor')
                  ->setHtmlAttribute("class", "checkbox")
                  ->getControlPrototype()
                  ->setAttribute("onchange", "$(this).parents('form').submit();");

        $container->addCheckbox("group_by_name", "Seskupit podle názvu: ")
                  ->setDefaultValue(true)
                  ->setHtmlId('group_by_name')
                  ->setHtmlAttribute("class", "checkbox")
                  ->getControlPrototype()
                  ->setAttribute("onchange", "$(this).parents('form').submit();");

       $container->getForm()->onSubmit[] = function ($form) use ($grid) {
    $values = $form->getValues();
    
    $values->group_action->histori = $values->group_action->histori ?? null;
    $values->group_action->group_by_name = $values->group_action->group_by_name ?? null;
    
    // ✅ ZÁKLADNÍ logika - bez hacku na detail
    if ($values->group_action->group_by_name) {
        $grid->setDataSource($grid->presenter->BaseModel->getDataSourceGrouped(null, $values->group_action->histori));
        $grid->getSessionData()->group_by_name = true;
        
        // ❌ ODSTRANIT tento hack - metoda neexistuje:
        /*
        $grid->getItemsDetail()->setDataSourceCallback(function($id) use ($grid) {
            return $grid->presenter->BaseModel->getDataSourceZjednodusene();
        });
        */
    } else {
        $grid->setDataSource($grid->presenter->BaseModel->getDataSourceZjednodusene(null, $values->group_action->histori));
        unset($grid->getSessionData()->group_by_name);
    }
    
    if ($values->group_action->histori) {
        $grid->getSessionData()->histori = $values->group_action->histori;
    } else {
        unset($grid->getSessionData()->histori);
        $values->group_action->histori = NULL;
    }
    
    $grid->reload();
};

        if ($prava == '9' || $prava == '2') {
            $grid->addToolbarButton("new")
                 ->setText("Nový lék")
                 ->setClass("btn btn-success")
                 ->setIcon("plus")
                 ->setTitle("Přidá nový lék");
        }

        $grid->addAction('sukl', 'Sukl', 'web')
             ->addAttributes(["target" => "_blank"])
             ->setClass("btn btn-info")
             ->setIcon("info");

        if ($prava == '9' || $prava == '2') {
            $grid->addAction('edit', 'Editace', 'edit')
                 ->setClass("btn btn-warning")
                 ->setIcon("pencil");
        }

        return $grid;
    }

public function setDGGrid(\Ublaboo\DataGrid\DataGrid $grid, $ID_LEKY) {
    $grid->setPrimaryKey("ID");
    $grid->setTranslator($this->getCsTranslator());
    $grid->setColumnsHideable();

    $grid->addColumnText('ID', '#')
         ->setSortable()
         ->setDefaultHide()
         ->setFilterText();

   
    $grid->addColumnText('ID_LEKY', 'Kód léku')
         ->setSortable()
         ->setDefaultHide()  
         ->setFilterText();

  
    $grid->addColumnText('ORGANIZACE', 'Organizace')
         ->setSortable()
         ->setDefaultHide()  
         ->setFilterText();

    $grid->addColumnText('LEK_NAZEV', 'Lék')
         ->setSortable()
         ->setFilterText();

    $grid->addColumnText('DG_NAZEV', 'Název DG')
         ->setSortable()
         ->setFilterText();

    $grid->addColumnText('111_RL', '111 - Revizní lékař')
         ->setSortable()
         ->setReplacement(\App\LekyModule\Presenters\ZjednodusenePresenter::TRUEORFALSE)
         ->setFilterText();

    $grid->addColumnText('111_POZNAMKA', '111 - Poznámka')
         ->setSortable()
         ->setReplacement(["" => "-"])
         ->setFilterText();

    $grid->addColumnText('VILP', 'VILP')
         ->setSortable()
         ->setReplacement(\App\LekyModule\Presenters\ZjednodusenePresenter::TRUEORFALSE)
         ->setFilterText();

    $grid->addColumnDateTime('DG_PLATNOST_OD', 'Platnost od')
         ->setFitContent()
         ->setFormat("d.m.Y")
         ->setSortable()
         ->setAlign('left')
         ->setFilterDate()
         ->setAttribute("data-date-language", "cs");

    $grid->addColumnDateTime('DG_PLATNOST_DO', 'Platnost do')
         ->setFitContent()
         ->setFormat("d.m.Y")
         ->setSortable()
         ->setAlign('left')
         ->setFilterDate()
         ->setAttribute("data-date-language", "cs");

    return $grid;
}
}