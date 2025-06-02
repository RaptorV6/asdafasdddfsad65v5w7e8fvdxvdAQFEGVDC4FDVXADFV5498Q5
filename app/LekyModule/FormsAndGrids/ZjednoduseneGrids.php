<?php
// app/LekyModule/FormsAndGrids/ZjednoduseneGrids.php - PŘEPSAT KOMPLETNĚ

namespace App\LekyModule\Grids;

use Ublaboo\DataGrid\AkesoGrid;
use \Nette\Utils\Html;
use \Nette\Forms\Container;
use Ublaboo\DataGrid\Column\Action\Confirmation\StringConfirmation;

class ZjednoduseneGridFactory extends \App\Factory\BaseDataGridFactory {

    public function __construct(\Nette\Security\User $user, \Dibi\Connection $db, array $parameters) {
        //parent::__construct($user, $db, $parameters);
    }

    public function setZjednoduseneGrid(AkesoGrid $grid, $prava, $poj, $defaultHodnoty): AkesoGrid {
        $grid->setPrimaryKey("ID_LEKY");
        $grid->setStrictSessionFilterValues(false);
        $grid->setColumnsHideable();
        
        $grid->setDefaultSort(array('ID_LEKY' => 'DESC'));

        // ✅ ZJEDNODUŠENÉ SLOUPCE (pouze požadované)
        $grid->addColumnText('ORGANIZACE', 'Organizace')
             ->setSortable()
             ->setFilterMultiSelect(\App\LekyModule\Presenters\ZjednodusenePresenter::ORGANIZACE)
             ->addAttribute('class', 'multiselect');

        $grid->addColumnText('NAZ', 'Název')
             ->setSortable()
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

        // ✅ STAVY POJIŠŤOVEN (zachováno stejné jako v původním)
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

        // ✅ DG DETAIL (zachováno stejné jako v původním)
        $grid->setItemsDetail(true, "ID_LEKY")
             ->setClass("btn btn-primary btn-sm ajax")
             ->setTitle("Diagnostické skupiny")
             ->setText("Detail (Info)")
             ->setIcon("arrow-down")
             ->setTemplateParameters(["ID_LEKY"=>"ID_LEKY"])
             ->setType("template")
             ->setTemplate(__DIR__."/../templates/Zjednodusene/itemDetail.latte");

        // ✅ HROMADNÉ AKCE (zachováno stejné jako v původním)
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

        // ✅ ZKOPÍROVÁNO Z OFICIÁLNÍ VERZE - PŘESNĚ STEJNÉ
        $container = $grid->getComponent("filter-group_action");
        /** @var \Nette\Forms\Container $container */
        
        $container->addCheckbox("histori", "Akord léky: ")
                  ->setDefaultValue($grid->getSessionData("histori"))
                  ->setHtmlId('histor')
                  ->setHtmlAttribute("class", "checkbox")
                  ->getControlPrototype()
                  ->setAttribute("onchange", "$(this).parents('form').submit();");

        // ✅ ZKOPÍROVÁNO Z OFICIÁLNÍ VERZE
        $container->getForm()
        ->onSubmit[] = function ($form) use ($grid) {
            $values = $form->getValues();
            $values->group_action->lekarnaVyber = $values->group_action->lekarnaVyber ?? null;
            $values->group_action->histori = $values->group_action->histori ?? null;
            if ($values->group_action->lekarnaVyber) {
                $grid->setDataSource($grid->presenter->BaseModel->getDataSource($values->group_action->lekarnaVyber));
                $grid->getSessionData()->lekarnaVyber = $values->group_action->lekarnaVyber;
            } elseif (!$values->group_action->lekarnaVyber) {
                unset($grid->getSessionData()->lekarnaVyber);
                $values->group_action->lekarnaVyber = NULL;
                $grid->setDataSource($grid->presenter->BaseModel->getDataSource($values->group_action->lekarnaVyber));
            }
            if ($values->group_action->histori) {
                $grid->setDataSource($grid->presenter->BaseModel->getDataSource($values->group_action->lekarnaVyber, $values->group_action->histori));
                $grid->getSessionData()->histori = $values->group_action->histori;
            } elseif (!$values->group_action->lekarnaVyber) {
                unset($grid->getSessionData()->histori, $values->group_action->histori);
                $values->group_action->histori = NULL;
                $grid->setDataSource($grid->presenter->BaseModel->getDataSource($values->group_action->lekarnaVyber, $values->group_action->histori));
            }
            $grid->reload();
        };

        // ✅ AKCE TLAČÍTKA (zachováno stejné jako v původním)
        if ($prava == '9' || $prava == '2') {
            $grid->addToolbarButton("new")
                 ->setText("Nový lék")
                 ->setClass("btn btn-success")
                 ->setIcon("plus")
                 ->setTitle("Přidá nový lék");
        }

        // ✅ SUKL ODKAZ (zachováno stejné jako v původním)
        $grid->addAction('sukl', 'Sukl', 'web')
             ->addAttributes(["target" => "_blank"])
             ->setClass("btn btn-info")
             ->setIcon("info");

        // ✅ EDITACE (zachováno stejné jako v původním)
        if ($prava == '9' || $prava == '2') {
            $grid->addAction('edit', 'Editace', 'edit')
                 ->setClass("btn btn-warning")
                 ->setIcon("pencil");
        }

        return $grid;
    }

    // ✅ DG GRID (zachováno stejné jako v původním)
    public function setDGGrid(\Ublaboo\DataGrid\DataGrid $grid, $ID_LEKY) {
        $grid->setPrimaryKey("ID");
        $grid->setTranslator($this->getCsTranslator());
        $grid->setColumnsHideable();

        $grid->addColumnText('ID', '#')
             ->setSortable()
             ->setFilterText();

        $grid->addColumnText('ID_LEKY', 'Lék')
             ->setSortable()
             ->setFilterText();

        $grid->addColumnText('ORGANIZACE', 'Organizace')
             ->setSortable()
             ->setFilterMultiSelect(\App\LekyModule\Presenters\ZjednodusenePresenter::ORGANIZACE);

        $grid->addColumnText('POJISTOVNA', 'Pojišťovna')
             ->setSortable()
             ->setReplacement(['0'=>'Všechny'] + \App\LekyModule\Presenters\ZjednodusenePresenter::POJISTOVNY)
             ->setFilterText();

        $grid->addColumnText('DG_NAZEV', 'Název DG')
             ->setSortable()
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

        $grid->addInlineAdd()
             ->setPositionTop()
             ->onControlAdd[] = function (Container $container) use($ID_LEKY){
                 $container->addText("ID_LEKY", "ID Léku")
                          ->setDefaultValue($ID_LEKY)
                          ->setHtmlAttribute('readonly');

                 $container->addSelect('ORGANIZACE','ORGANIZACE')
                          ->setItems(\App\LekyModule\Presenters\ZjednodusenePresenter::ORGANIZACE);

                 $container->addSelect('POJISTOVNA','Pojišťovna')
                          ->setItems([0=>'Všechny'] + \App\LekyModule\Presenters\ZjednodusenePresenter::POJISTOVNY);
                 
                 $container->addText("DG_NAZEV", "DG Název")
                          ->setHtmlAttribute('data-autocomplete-dg')
                          ->setNullable();
             
                 $container->addCheckbox('VILP', 'VILP')
                          ->setHtmlAttribute('class', 'checkbox_style');

                 $container->addText('DG_PLATNOST_OD','Platnost od')
                          ->setHtmlType('date')
                          ->setDefaultValue(date("Y-m-d"))
                          ->setNullable();

                 $container->addText('DG_PLATNOST_DO','Platnost do')
                          ->setHtmlType('date')
                          ->setDefaultValue(date("Y-m-d"))
                          ->setNullable();
             };

        $grid->addInlineEdit()
             ->onControlAdd[] = function(Container $container): void {
                 $container->addText("ID_LEKY", "ID Léku")
                          ->setHtmlAttribute('readonly');

                 $container->addSelect('ORGANIZACE','ORGANIZACE')
                          ->setHtmlAttribute('readonly')
                          ->setItems(\App\LekyModule\Presenters\ZjednodusenePresenter::ORGANIZACE);

                 $container->addSelect('POJISTOVNA', 'Pojišťovna')
                          ->setHtmlAttribute('readonly')
                          ->setItems([0=>'Všechny'] + \App\LekyModule\Presenters\ZjednodusenePresenter::POJISTOVNY);
                 
                 $container->addText("DG_NAZEV", "DG Název")
                          ->setHtmlAttribute('readonly')
                          ->setNullable();
             
                 $container->addCheckbox('VILP', 'VILP')
                          ->setHtmlAttribute('class', 'checkbox_style');

                 $container->addText('DG_PLATNOST_OD','Platnost od')
                          ->setHtmlType('date')
                          ->setNullable();

                 $container->addText('DG_PLATNOST_DO','Platnost do')
                          ->setHtmlType('date')
                          ->setNullable();
             };

        $grid->getInlineEdit()->onSetDefaults[] = function (Container $container, $item): void {
            $ID_LEKY = $item['ID_LEKY'];
            $ORGANIZACE = $item['ORGANIZACE'];
            $POJISTOVNA = $item['POJISTOVNA'];
            $PLATNOST_OD = $item['DG_PLATNOST_OD'] !== null ? \DateTime::createFromFormat('d.m.Y', $item['DG_PLATNOST_OD'])->format('Y-m-d') : NULL;
            $PLATNOST_DO = $item['DG_PLATNOST_DO'] !== null ? \DateTime::createFromFormat('d.m.Y', $item['DG_PLATNOST_DO'])->format('Y-m-d') : NULL;
            $container->setDefaults([
                'ID_LEKY' => $ID_LEKY,
                'ORGANIZACE' => $ORGANIZACE,
                'POJISTOVNA' => $POJISTOVNA,
                'VILP' => $item['VILP'],
                'DG_NAZEV' => $item['DG_NAZEV'],
                'DG_PLATNOST_OD' => $PLATNOST_OD,
                'DG_PLATNOST_DO' => $PLATNOST_DO,
            ]);
        };

        return $grid;
    }
}