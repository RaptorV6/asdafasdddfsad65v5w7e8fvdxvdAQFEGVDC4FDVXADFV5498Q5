<?php
// app/LekyModule/FormsAndGrids/ZjednoduseneGrids.php

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
        
        // Nastavení defaultního řazení jako v původním gridu
        $grid->setDefaultSort(array('ID_LEKY' => 'DESC'));

        // Pouze požadované sloupce
        
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
             ->setDefaultHide() // Skrýt ve výchozím stavu
             ->setFilterText();

        $grid->addColumnText('UCINNA_LATKA', 'Učinná látka')
             ->setSortable()
             ->setDefaultHide() // Skrýt ve výchozím stavu
             ->setFilterText();

        $grid->addColumnText('BIOSIMOLAR', 'Biosimilar')
             ->setSortable()
             ->setDefaultHide() // Skrýt ve výchozím stavu
             ->setFilterText();

        $grid->addColumnText('ATC', 'ATC')
             ->setSortable()
             ->setReplacement(["" => "-"])
             ->setFilterText()
             ->setSplitWordsSearch(true);

        // Stavy pojišťoven - pouze ty požadované
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

        // Přidání DG detailu (rozkliknutí) - změna textu
        $grid->setItemsDetail(true, "ID_LEKY")
             ->setClass("btn btn-primary btn-sm ajax")
             ->setTitle("Detail informace o léku")
             ->setText("Detail (Info)")
             ->setIcon("arrow-down")
             ->setTemplateParameters(["ID_LEKY"=>"ID_LEKY"])
             ->setType("template")
             ->setTemplate(__DIR__."/../templates/Zjednodusene/itemDetail.latte");

        // Možnost hromadných akcí pro správce
        if (($prava == '9' || $prava == '2') && $poj == 1) {
            $grid->addGroupButtonAction('Hromadná změna')
                 ->setClass("btn btn-success")
                 ->setAttribute("style", "float:initial !important;")
                 ->onClick[] = function ($id) use ($grid) {
                     $grid->presenter->redirect(':Leky:Zjednodusene:Hromad', json_encode($id));
                 };
        }

        // Přidání globálního vyhledávání pomocí group_action containeru
        $container = $grid->getComponent("filter-group_action");
        /** @var \Nette\Forms\Container $container */
        
        $container->addText("globalSearch", "Globální hledání:")
                  ->setHtmlAttribute("placeholder", "Název, ATC, účinná látka...")
                  ->setHtmlAttribute("class", "form-control")
                  ->setHtmlAttribute("style", "width:250px !important; margin-right: 15px")
                  ->setDefaultValue($grid->getSessionData("globalSearch"));

        $container->getForm()->onSubmit[] = function ($form) use ($grid) {
            $values = $form->getValues();
            if ($values->group_action->globalSearch) {
                $searchTerm = $values->group_action->globalSearch;
                $grid->saveSessionData('globalSearch', $searchTerm);
                
                // Filtrování datasource podle vyhledávacího termínu
                $filteredData = $grid->presenter->BaseModel->getDataSourceWithGlobalSearch(
                    $searchTerm,
                    $grid->getSessionData()->lekarnaVyber ?? null, 
                    $grid->getSessionData()->histori ?? null
                );
                $grid->setDataSource($filteredData);
            } else {
                $grid->saveSessionData('globalSearch', null);
                $grid->setDataSource($grid->presenter->BaseModel->getDataSource(
                    $grid->getSessionData()->lekarnaVyber ?? null, 
                    $grid->getSessionData()->histori ?? null
                ));
            }
            $grid->reload();
        };

        if ($prava == '9' || $prava == '2') {
            $grid->addToolbarButton("new")
                 ->setText("Nový lék")
                 ->setClass("btn btn-success")
                 ->setIcon("plus")
                 ->setTitle("Přidá nový lék");

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

        $grid->addColumnText('NAZ', 'Lék')
             ->setSortable()
             ->setFilterText();

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
            $POJISTOVNA = $item['POJISTOVNA'];
            $PLATNOST_OD = $item['DG_PLATNOST_OD'] !== null ? \DateTime::createFromFormat('d.m.Y', $item['DG_PLATNOST_OD'])->format('Y-m-d') : NULL;
            $PLATNOST_DO = $item['DG_PLATNOST_DO'] !== null ? \DateTime::createFromFormat('d.m.Y', $item['DG_PLATNOST_DO'])->format('Y-m-d') : NULL;
            $container->setDefaults([
                'ID_LEKY' => $ID_LEKY,
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
