<?php
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
    
        // ✅ DEFAULT data source podle group toggle
        $groupByName = $grid->getSessionData('group_by_name') ?? true;
        
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
        
        // ✅ ZÍSKAT FILTR ORGANIZACE z hlavního gridu
        $mainGrid = $this->getComponent('zjednoduseneDataGrid');
        $filterValues = $mainGrid->getSessionData('filter') ?? [];
       $organizaceFilter = $this->getSession('zjednodusene')->organizaceFilter ?? null;
        
        // ✅ PŘEDAT filtr do data source
        $grid->setDataSource($this->BaseModel->getDataSource_DG($ID_LEKY, $organizaceFilter));
        
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

public function renderDefault() {
    $this->template->nadpis = 'zjednodušený přehled léků';
        if ($this->isAjax() && $this->getHttpRequest()->getPost('filter')) {
        $filterData = $this->getHttpRequest()->getPost('filter');
        if (isset($filterData['ORGANIZACE'])) {
            $this->getSession('zjednodusene')->organizaceFilter = $filterData['ORGANIZACE'];
        }
    }
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

    public function actionWeb($ID_LEKY) {
        $this->redirectUrl('https://prehledy.sukl.cz/prehled_leciv.html#/leciva/' . $ID_LEKY);
    }

    public function handleDgskup($term) {
        $fristHalfItems = $this->BaseModel->getDg($term);
        $this->sendResponse(new \Nette\Application\Responses\JsonResponse($fristHalfItems));
    }
}