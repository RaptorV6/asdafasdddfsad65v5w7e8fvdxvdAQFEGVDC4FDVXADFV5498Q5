<?php

namespace App\Presenters;

/**
 * Secure presenter pro přihlášené uživatele
 */
abstract class SecurePresenter extends BasePresenter {

    /** @var \App\Components\Model\Datagridmodul @inject*/
    public $Ciselnik;

	public function startup() {
            parent::startup();
            if($this->user->isLoggedIn()) {
                $this->Auth->insertAcessLog($this->user->getId());
                $this->template->logged = $this->user->isLoggedIn();
                $this->template->prava = $this->user->getIdentity()->prava;
            } else {
                $this->template->logged = false;
                $this->template->prava = false;
                $this->redirect(':Homepage:default');
            }
	}    
}
