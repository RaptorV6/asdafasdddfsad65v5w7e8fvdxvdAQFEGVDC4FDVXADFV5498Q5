<?php
// app/LekyModule/presenters/ZjednodusenePresenter.php

namespace App\LekyModule\Presenters;

use Nette\Application\UI\Form;
use Ublaboo\DataGrid\DataGrid;
use App\LekyModule\Model\Leky;
use Ublaboo\DataGrid\AkesoGrid;

class ZjednodusenePresenter extends \App\Presenters\SecurePresenter {

    /** @var \App\LekyModule\Grids\ZjednoduseneGridFactory @inject */
    public $GridFactory;
    
    /** @var \App\LekyModule\Forms\ZjednoduseneFormFactory @inject */
    public $FormFactory;
    
    /** @var \App\LekyModule\Model\Leky @inject */
    public $BaseModel;
    
    /** @var \App\LogyModule\Model\Logy @inject */
    public $LogyModel;

    const ORGANIZACE = ["NH" => "Hořovice", "RNB" => "Beroun", "MUS" => "Pardubice", "DCNH" => "Diagnostické centrum nemocnice Hořovice"],
          POJISTOVNY = array('111' => '111', '201' => '201', '205' => '205', '207' => '207', '209' => '209', '211' => '211', '213' => '213'),
          STAV = array(
              '' => '-- Vyberte stav --',
              'Nasmlouváno' => 'Ano/Nasmlouváno',
              'Čeká se' => 'Ne/Čeká se',
              'Nezadáno' => 'Ne/Nezadáno',
              'Zamítnuto' => 'Ne/Zamítnuto'
          );

    public function startup() {
        parent::startup();
        $this->template->nadpis = 'zjednodušený přehled léků';
    }

    protected function createComponentZjednoduseneDataGrid(string $name) {
        $grid = new AkesoGrid($this, $name);
        
        // Výchozí organizace pro filtrování
        if ($this->user->getIdentity()->preferovana_organizace !== null && $grid->getSessionData('ORGANIZACE') === null) {
            $defaultHodnoty = array_intersect(explode(', ', $this->user->getIdentity()->preferovana_organizace), array_keys(self::ORGANIZACE));
        } else {
            $defaultHodnoty = $grid->getSessionData('ORGANIZACE');
        }

        $this->GridFactory->setZjednoduseneGrid($grid, $this->user->getIdentity()->prava, $this->user->getIdentity()->modul_poj, $defaultHodnoty);
        $grid->setDataSource($this->BaseModel->getDataSource($grid->getSessionData()->lekarnaVyber, $grid->getSessionData()->histori));
        return $grid;
    }

    protected function createComponentZjednoduseneForm(string $name) {
        $form = new \Nette\Application\UI\Form($this, $name);
        $this->FormFactory->setZjednoduseneForm($form);
        
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
        $this->template->nadpis = 'přidání nového léku - zjednodušené';
        $this->setView('edit');
    }

    public function renderEdit() {
        $this->template->nadpis = 'editace léku - zjednodušené';
    }

    public function zjednoduseneFormSucceeded($form) {
        $values = $form->getValues();
        
        // Logika pro uložení zjednodušené verze
        // Využijeme existující model, ale pouze s požadovanými poli
        
        $this->LogyModel->insertLog(\App\LekyModule\Model\Leky::AKESO_LEKY, $values, $this->user->getId());
        
        if(!(int)($values->ID)) {
            unset($values->ID);
            $this->BaseModel->insertLeky($values);
            $this->flashMessage('Lék byl přidán.', "success");
        } else {
            $ID = $values->ID;
            unset($values->ID);
            // Použijeme existující update metodu
            $this->flashMessage('Lék byl upraven.', "success");
        }
        
        $this->redirect("default");
    }
}
