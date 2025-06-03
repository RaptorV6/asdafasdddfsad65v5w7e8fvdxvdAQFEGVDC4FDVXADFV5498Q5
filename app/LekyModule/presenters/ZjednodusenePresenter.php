<?php
// app/LekyModule/presenters/ZjednodusenePresenter.php

namespace App\LekyModule\Presenters;

use Nette\Application\UI\Form;
use Nette\Application\UI\Multiplier;
use Ublaboo\DataGrid\DataGrid;
use App\LekyModule\Model\LekyZjednoduseny;
use Ublaboo\DataGrid\AkesoGrid;

class ZjednodusenePresenter extends \App\Presenters\SecurePresenter {

    /** @var \App\LekyModule\Grids\ZjednoduseneGridFactory @inject */
    public $GridFactory;
    
    /** @var \App\LekyModule\Forms\ZjednoduseneFormFactory @inject */
    public $FormFactory;
    
    /** @var \App\LekyModule\Model\LekyZjednoduseny @inject */
    public $BaseModel;
    
    /** @var \App\LogyModule\Model\Logy @inject */
    public $LogyModel;

    const ORGANIZACE = ["NH" => "Hořovice", "RNB" => "Beroun", "MUS" => "Pardubice", "DCNH" => "Diagnostické centrum nemocnice Hořovice"],
          POJISTOVNY = array('111' => '111', '201' => '201', '205' => '205', '207' => '207', '209' => '209', '211' => '211', '213' => '213'),
          TRUEORFALSE = [ "" => "Ne", "0" => "Ne", "1" => "Ano"],
          STAV = array(
              '' => '-- Vyberte stav --',
              'Nasmlouváno' => 'Ano/Nasmlouváno',
              'Čeká se' => 'Ne/Čeká se',
              'Nezadáno' => 'Ne/Nezadáno',
              'Zamítnuto' => 'Ne/Zamítnuto'
          ),
          ORGANIZACE_VISIBLE = ["NH", "RNB", "MUS", "DCNH"];

    public function startup() {
        parent::startup();
        $this->template->nadpis = 'zjednodušený přehled léků';
    }

    protected function createComponentZjednoduseneDataGrid(string $name) {
        $grid = new AkesoGrid($this, $name);
        
        if ($this->user->getIdentity()->preferovana_organizace !== null && $grid->getSessionData('ORGANIZACE') === null) {
            $defaultHodnoty = array_intersect(explode(', ', $this->user->getIdentity()->preferovana_organizace), self::ORGANIZACE_VISIBLE);
        } else {
            $defaultHodnoty = $grid->getSessionData('ORGANIZACE');
        }

        $this->GridFactory->setZjednoduseneGrid($grid, $this->user->getIdentity()->prava, $this->user->getIdentity()->modul_poj, $defaultHodnoty);
    
    // ✅ KLÍČOVÁ OPRAVA - nastavit správný default data source
    $groupByName = $grid->getSessionData('group_by_name') ?? true; // Default true
    
    if ($groupByName) {
        $grid->setDataSource($this->BaseModel->getDataSourceGrouped(
            $grid->getSessionData('lekarnaVyber') ?? null, 
            $grid->getSessionData('histori') ?? null
        ));
    } else {
        $grid->setDataSource($this->BaseModel->getDataSourceZjednodusene(
            $grid->getSessionData('lekarnaVyber') ?? null, 
            $grid->getSessionData('histori') ?? null
        ));
    }
    
    return $grid;
}

    public function createComponentDGDataGrid(string $name): Multiplier{
        return new Multiplier(function ($ID_LEKY) {

            $grid = new DataGrid(null, $ID_LEKY);
            $this->GridFactory->setDGGrid($grid, $ID_LEKY);
            
            $grid->setDataSource($this->BaseModel->getDataSource_DG_WithName($ID_LEKY));
            
            $grid->getInlineAdd()->onSubmit[] = function(\Nette\Utils\ArrayHash $values): void {
                $this->BaseModel->set_pojistovny_dg($values);
                $this->flashMessage("Vkládání do databáze proběhlo v pořádku", 'success');
                $this->redirect('this');
            };
            
            $grid->getInlineEdit()->onSubmit[] = function($id, $values): void {
                $this->BaseModel->set_pojistovny_dg_edit($values);
                $this->flashMessage("Editace proběhla v pořádku", 'success');
                $this->redirect('this');
            };
            
            return $grid;
        });
    }

    protected function createComponentZjednoduseneForm(string $name) {
        $form = new \Nette\Application\UI\Form($this, $name);
        
        $lek = $this->BaseModel->getLeky($this->getParameter('ID_LEKY'));

        if ($lek && isset($lek->ORGANIZACE)) { // ✅ Ošetření přístupu
            $lek->ORGANIZACE = explode(", ", $lek->ORGANIZACE);
        }
        $lek['POJ'] = [];
        $values = '';

        foreach (self::ORGANIZACE_VISIBLE as $org) {
            $lek[$org] = \Nette\Utils\ArrayHash::from($this->BaseModel->getPojistovny($this->getParameter('ID_LEKY'), $org));
            if (isset($lek->$org)) {
                foreach ($lek->$org as $value => $key) {
                    // Oprava RL pole - převod na string index
                    if ($lek[$org][$value]['RL'] === '') {
                        $lek[$org][$value]['RL'] = '';
                    } elseif ($lek[$org][$value]['RL'] == 1 || $lek[$org][$value]['RL'] == 0) {
                        $lek[$org][$value]['RL'] = (string)$lek[$org][$value]['RL'];
                        $lek[$org][$value]['Revizak'] = true;
                    } else {
                        $lek[$org][$value]['Revizak'] = false;
                    }
                    $lek[$org][$value]['DG'] = $this->BaseModel->getPojistovny_DG($this->getParameter('ID_LEKY'), $org, $value);
                    if (!in_array($value, $lek['POJ'])) {
                        $values = $values . ", " . $value;
                    }
                }
            }
        }
        if ($values) {
            $lek['POJ'] = explode(', ', trim($values, ", "));
        }

        $this->FormFactory->setZjednoduseneForm($form);
        $form->onError[] = function ($form) {
            \App\LekyModule\Presenters\LekyPresenter::processFormErrors($form, $this);
        };
        if ($lek && !empty($lek->ORGANIZACE)) { // ✅ Ošetření přístupu
            $lek->ORGANIZACE = array_filter($lek->ORGANIZACE);
        }
        $form->setDefaults($lek);
        
        $form->addGroup();
        $form->addSubmit('send', 'Uložit')
             ->setHtmlAttribute('class', 'btn btn-success button btn-block');
        
        $form->onSuccess[] = [$this, "zjednoduseneFormSucceeded"];
        return $form;
    }

    public function renderDefault() {
        $this->template->nadpis = 'zjednodušený přehled léků';
    }

    public function renderNew() {
        $session = $this->getComponent('zjednoduseneDataGrid');
        $savedata = $this->getComponent('zjednoduseneForm');
        
        if ($this->user->getIdentity()->preferovana_organizace !== null && $session->getSessionData('ORGANIZACE') === null) {
            $defaultHodnoty = array_intersect(explode(', ', $this->user->getIdentity()->preferovana_organizace), self::ORGANIZACE_VISIBLE);
        } else {
            $defaultHodnoty = $session->getSessionData('ORGANIZACE');
        }
        
        $savedata->setDefaults(array('ORGANIZACE' => $defaultHodnoty));
        $this->template->nadpis = 'přidání nového léku - zjednodušené';
        $this->setView('edit');
    }

    public function renderEdit() {
        $this->template->nadpis = 'editace léku - zjednodušené';
    }

    public function renderHromad($id) {
        $idparametr = explode(",", $this->getParameter('id'));
        $idparametr = preg_replace("/[^a-zA-Z 0-9]+/", "", $idparametr);
        $this->template->nadpis = 'hromadná změna stavu pojišťoven';
        $this->template->id = implode(',', $idparametr);
        $this->template->pojistovny = self::POJISTOVNY;
        $this->template->organizace = self::ORGANIZACE_VISIBLE;
    }

    public function renderHromadiag($id) {
        $idparametr = explode(",", $this->getParameter('id'));
        $idparametr = preg_replace("/[^a-zA-Z 0-9]+/", "", $idparametr);
        $this->template->nadpis = 'hromadná změna diagnostické skupiny';
        $this->template->id = implode(',', $idparametr);
        $this->template->pojistovny = self::POJISTOVNY;
        $this->template->organizace = self::ORGANIZACE_VISIBLE;
    }

    public function actionWeb($ID_LEKY) {
        $this->redirectUrl('https://prehledy.sukl.cz/prehled_leciv.html#/leciva/' . $ID_LEKY);
    }

    public function zjednoduseneFormSucceeded($form) {
        $values = $form->getValues();
        
        $this->LogyModel->insertLog(\App\LekyModule\Model\Leky::AKESO_LEKY, $values, $this->user->getId());
        
        if(!(int)($values->ID_LEKY)) {
            unset($values->ID_LEKY);
            $this->BaseModel->insertLeky($values);
            $this->flashMessage('Lék byl přidán.', "success");
        } else {
            $ID = $values->ID_LEKY;
            unset($values->ID_LEKY);
            $this->flashMessage('Lék byl upraven.', "success");
        }
        
        $this->redirect("default");
    }

    public function handleDgskup($term) {
        $fristHalfItems = $this->BaseModel->getDg($term);
        $this->sendResponse(new \Nette\Application\Responses\JsonResponse($fristHalfItems));
    }

    protected function createComponentHromadForm(string $name) {
        $form = new \Nette\Application\UI\Form($this, $name);
        $form->addHidden('ID')
             ->setRequired('Musí být zadaný "%label"');
             
        $form->addMultiSelect('ORGANIZACE', 'Organizace')
             ->setHtmlAttribute('class', 'multiselect')
             ->setItems(self::ORGANIZACE)
             ->setRequired('Musí být zadaný "%label"');

        $form->addMultiSelect('POJ', 'Pojišťovny')
             ->setHtmlAttribute('class', 'multiselect')
             ->setItems(self::POJISTOVNY)
             ->setRequired('Musí být zadaný "%label"');

        $value['ID'] = $this->getParameter('id');
        $form->setDefaults($value);
        $form->addGroup();
        $form->addSubmit('send', 'Uložit')
             ->setHtmlAttribute('class ', 'btn btn-success button btn-block');
        $form->onSuccess[] = [$this, "hromadFormSucceeded"];
        return $form;
    }

    protected function createComponentHromadiagForm(string $name) {
        $form = new \Nette\Application\UI\Form($this, $name);
        $this->FormFactory->setHromadDiagForm($form);

        $value['ID'] = $this->getParameter('id');
        $form->setDefaults($value);
        $form->addGroup();
        $form->addSubmit('send', 'Uložit')
             ->setHtmlAttribute('class ', 'btn btn-success button btn-block');
        $form->onSuccess[] = [$this, "hromadDiagFormSucceeded"];
        return $form;
    }

    public function hromadFormSucceeded($form) {
        $this->flashMessage('Záznamy byly upraveny.', "success");
        $this->redirect('default');
    }

    public function hromadDiagFormSucceeded($form) {
        $this->flashMessage('Záznamy byly upraveny.', "success");
        $this->redirect('default');
    }
}
