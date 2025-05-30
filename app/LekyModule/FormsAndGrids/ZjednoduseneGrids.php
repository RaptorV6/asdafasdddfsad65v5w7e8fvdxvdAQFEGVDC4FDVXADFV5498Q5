<?php
// app/LekyModule/FormsAndGrids/ZjednoduseneGrids.php

namespace App\LekyModule\Grids;

use Ublaboo\DataGrid\AkesoGrid;
use \Nette\Utils\Html;
use \Nette\Forms\Container;

class ZjednoduseneGridFactory extends \App\Factory\BaseDataGridFactory {

    public function __construct(\Nette\Security\User $user, \Dibi\Connection $db, array $parameters) {
        //parent::__construct($user, $db, $parameters);
    }

    public function setZjednoduseneGrid(AkesoGrid $grid, $prava, $poj, $defaultHodnoty): AkesoGrid {
        $grid->setPrimaryKey("ID_LEKY");
        $grid->setStrictSessionFilterValues(false);
        $grid->setColumnsHideable();

        // Pouze požadované sloupce
        
        $grid->addColumnText('ORGANIZACE', 'Organizace')
             ->setSortable()
             ->setFilterMultiSelect(\App\LekyModule\Presenters\ZjednodusenePresenter::ORGANIZACE)
             ->addAttribute('class', 'multiselect');

        $grid->addColumnText('NAZ', 'Název')
             ->setSortable()
             ->setFilterText()
             ->setSplitWordsSearch(true);

        $grid->addColumnText('POZNAMKA', 'Poznámka')
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
                         $item[$value . "_STAV"] = $item[$value . "_STAV"] . ' ' . $item[$value . "_NASMLOUVANO_OD"] ?? "Nezadáno";
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
}
