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








// V ZjednodusenePresenter.php
public function createComponentDGDataGrid(string $name): Multiplier{
    error_log("=== CREATING DG DATA GRID ===");
    
    return new Multiplier(function ($ID_LEKY) {
        error_log("=== MULTIPLIER CALLBACK FOR ID_LEKY: $ID_LEKY ===");

        $grid = new DataGrid(null, $ID_LEKY);
        error_log("=== GRID CREATED ===");
        
        $this->GridFactory->setDGGrid($grid, $ID_LEKY);
        error_log("=== GRID FACTORY SET ===");
        
        $grid->setDataSource($this->BaseModel->getDataSource_DG($ID_LEKY));
        error_log("=== DATA SOURCE SET ===");
        
        // ✅ ODEBRÁN problémový filter handler
        
        error_log("=== RETURNING GRID ===");
        return $grid;
    });
}

public function processSignal(): void {
    error_log("=== PROCESS SIGNAL CALLED ===");
    $signal = $this->getSignal();
    error_log("SIGNAL: " . ($signal ? print_r($signal, true) : 'NULL'));
    error_log("POST: " . print_r($_POST, true));
    
    // ✅ OPRAVENÁ podmínka - zachytí filter submit s inline_edit daty
    if ($signal && is_array($signal) && count($signal) >= 2 && 
        strpos($signal[0], 'dGDataGrid-') === 0 && 
        $signal[1] === 'submit' &&  // ✅ změněno z 'inlineEdit' na 'submit'
        isset($_POST['inline_edit'])) {
        
        error_log("=== PROCESSING INLINE EDIT ===");
        $inlineData = $_POST['inline_edit'];
        error_log("INLINE EDIT RAW DATA: " . print_r($inlineData, true));
        
        // ✅ Najít původní záznam podle _id
        $id = $inlineData['_id'] ?? null;
        if ($id) {
            // ✅ Získat ID_LEKY z signálu (odstranit -filter část)
            preg_match('/dGDataGrid-(.+)-filter/', $signal[0], $matches);
            $ID_LEKY = $matches[1] ?? null;
            
            error_log("EXTRACTED ID_LEKY: $ID_LEKY");
            
            if ($ID_LEKY) {
                // ✅ Najít původní záznam
                $originalRecords = $this->BaseModel->getDataSource_DG($ID_LEKY);
                $targetRow = null;
                foreach ($originalRecords as $row) {
                    if ($row->ID == $id) {
                        $targetRow = $row;
                        break;
                    }
                }
                
                error_log("TARGET ROW: " . ($targetRow ? print_r($targetRow, true) : 'NOT FOUND'));
                
                if ($targetRow) {
                    // ✅ Sestavit data pro update
                    $editValues = [
                        'ID_LEKY' => $targetRow->ID_LEKY,
                        'ORGANIZACE' => $targetRow->ORGANIZACE,
                        'POJISTOVNA' => $targetRow->POJISTOVNA,
                        'DG_NAZEV' => $targetRow->DG_NAZEV,
                        'VILP' => isset($inlineData['VILP']) && $inlineData['VILP'] === 'on' ? 1 : 0,
                        'DG_PLATNOST_OD' => $inlineData['DG_PLATNOST_OD'] ?? null,
                        'DG_PLATNOST_DO' => $inlineData['DG_PLATNOST_DO'] ?? null,
                    ];
                    
                    error_log("FINAL UPDATE VALUES: " . print_r($editValues, true));
                    
                    try {
                      $result = $this->BaseModel->set_pojistovny_dg_edit($editValues);
error_log("UPDATE RESULT: " . ($result ? 'SUCCESS' : 'FAILED')); // ✅ Opraveno

// ✅ JEDNODUŠE - vždy success pokud nedojde k výjimce
$this->flashMessage("Editace proběhla v pořádku", 'success');

$this->redirect('this');
                        return; // Zastaví další zpracování
                    } catch (\Exception $e) {
                        error_log("UPDATE ERROR: " . $e->getMessage());
                        $this->flashMessage("Chyba při editaci: " . $e->getMessage(), 'error');
                        $this->redirect('this');
                        return;
                    }
                } else {
                    error_log("TARGET ROW NOT FOUND FOR ID: $id");
                }
            } else {
                error_log("ID_LEKY NOT EXTRACTED FROM SIGNAL");
            }
        } else {
            error_log("NO _id IN INLINE DATA");
        }
    }
    
    try {
        parent::processSignal();
    } catch (\Exception $e) {
        error_log("SIGNAL ERROR: " . $e->getMessage());
        throw $e;
    }
}

// ✅ Ostatní metody třídy
public function handleInlineEdit($id) {
    error_log("=== HANDLE INLINE EDIT SIGNAL CALLED ===");
    error_log("ID: $id");
    error_log("POST DATA: " . print_r($_POST, true));
    error_log("REQUEST DATA: " . print_r($this->getRequest()->getPost(), true));
}




    protected function createComponentZjednoduseneForm(string $name) {
        $form = new \Nette\Application\UI\Form($this, $name);
        
        $lek = $this->BaseModel->getLeky($this->getParameter('ID_LEKY'));

        if ($lek && isset($lek->ORGANIZACE)) {
            $lek->ORGANIZACE = explode(", ", $lek->ORGANIZACE);
        }
        $lek['POJ'] = [];
        $values = '';

        foreach (self::ORGANIZACE_VISIBLE as $org) {
            $lek[$org] = \Nette\Utils\ArrayHash::from($this->BaseModel->getPojistovny($this->getParameter('ID_LEKY'), $org));
            if (isset($lek->$org)) {
                foreach ($lek->$org as $value => $key) {
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
        if ($lek && !empty($lek->ORGANIZACE)) {
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
    
    $editMode = $this->getAction() === 'edit';
    
    if (!$editMode && empty($values->ID_LEKY) && !empty($values->ATC)) {
        $values->ID_LEKY = $values->ATC;
    }
    
    if (empty($values->ID_LEKY)) {
        $form->addError('ATC skupina musí být vyplněna - slouží jako identifikátor léku.');
        return;
    }
    
    // Doplň chybějící pole
    $values->DOP = $values->DOP ?? '';
    $values->SILA = $values->SILA ?? '';
    $values->BALENI = $values->BALENI ?? '';
    $values->ATC3 = $values->ATC3 ?? '';
    $values->UHR1 = $values->UHR1 ?? null;
    $values->UHR2 = $values->UHR2 ?? null;
    $values->UHR3 = $values->UHR3 ?? null;
    $values->CENA_FAKTURACE = $values->CENA_FAKTURACE ?? null;
    $values->CENA_MAX = $values->CENA_MAX ?? null;
    $values->CENA_VYROBCE_BEZDPH = $values->CENA_VYROBCE_BEZDPH ?? null;
    $values->CENA_SENIMED_BEZDPH = $values->CENA_SENIMED_BEZDPH ?? null;
    $values->CENA_MUS_PHARMA = $values->CENA_MUS_PHARMA ?? null;
    $values->CENA_MUS_NC_BEZDPH = $values->CENA_MUS_NC_BEZDPH ?? null;
    $values->CENA_MUS_NC = $values->CENA_MUS_NC ?? null;
    $values->UHRADA = $values->UHRADA ?? null;
    $values->KOMPENZACE = $values->KOMPENZACE ?? null;
    $values->BONUS = $values->BONUS ?? null;
    
    $this->LogyModel->insertLog(\App\LekyModule\Model\Leky::AKESO_LEKY, $values, $this->user->getId());
    
    // ✅ UKLÁDÁNÍ POJIŠŤOVEN A DG
    foreach (['MUS'] as $org) {
        foreach (['111', '201', '205', '207', '209', '211', '213'] as $pojKey) {
            if (isset($values->$org) && isset($values->$org[$pojKey]) && $values->$org[$pojKey]->STAV) {
                $pojData = $values->$org[$pojKey];
                
                // ✅ REVIZÁK
                if (isset($pojData->Revizak) && $pojData->Revizak) {
                    $pojData->RL = $pojData->RL ?? '0';
                } else {
                    $pojData->RL = '';
                }
                
                // ✅ UKLÁDÁNÍ DG - BEZ DELETE, jen MERGE
                if (isset($pojData->DG)) {
                    foreach ($pojData->DG as $dg) {
                        if (!empty($dg->DG_NAZEV)) {
                            $dg->ID_LEKY = $values->ID_LEKY;
                            $dg->ORGANIZACE = $org;
                            $dg->POJISTOVNA = $pojKey;
                            $this->BaseModel->insert_edit_pojistovny_dg($dg);
                        }
                    }
                }
                
                // ✅ UKLÁDÁNÍ POJIŠŤOVNY
                $pojData->ORGANIZACE = $org;
                $pojData->ID_LEKY = $values->ID_LEKY;
                $pojData->POJISTOVNA = $pojKey;
                $pojData->SMLOUVA = $this->setSmlouva($pojData->POJISTOVNA);
                $this->BaseModel->insert_edit_pojistovny($pojData);
            }
        }
    }
    
    // ✅ ZÁKLADNÍ LÉK - jen pro nové
    if (!$editMode) {
        if (is_array($values->ORGANIZACE)) {
            foreach ($values->ORGANIZACE as $key => $value) {
                $tempValues = clone $values;
                $tempValues->ORGANIZACE = $value;
                $this->BaseModel->insertLeky($tempValues);
            }
        } else {
            $this->BaseModel->insertLeky($values);
        }
        $this->flashMessage('Lék byl přidán.', "success");
    } else {
        $this->flashMessage('Lék byl upraven.', "success");
    }
    
    $this->redirect("default");
}



// ✅ PŘIDEJ METODU setSmlouva 
private function setSmlouva($poj, $smlouva = null) {
    if ($smlouva) {
        return $smlouva;
    } else {
        if ($poj == '111' || $poj == '205' || $poj == '207') {
            return 0;
        } elseif ($poj == '209' || $poj == '213') {
            return 1;
        } elseif ($poj == '201') {
            return 2;
        } elseif ($poj == '211') {
            return 3;
        }
    }
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
