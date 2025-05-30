<?php
// app/LekyModule/FormsAndGrids/ZjednoduseneFormFactory.php

namespace App\LekyModule\Forms;

use Nette\Application\UI\Form;
use Nette\Forms\Container;
use Nette\Security\User;

\Nette\Forms\Controls\BaseControl::$idMask = '%s';

class ZjednoduseneFormFactory {

    public function __construct(User $user, \Dibi\Connection $db, Array $parameters) {
        // constructor
    }

    public function setZjednoduseneForm(\Nette\Application\UI\Form $form) {
        
        $form->addHidden('ID', 'id');

        $form->addMultiSelect('ORGANIZACE', 'Organizace')
             ->setHtmlAttribute('class', 'multiselect')
             ->setItems(\App\LekyModule\Presenters\ZjednodusenePresenter::ORGANIZACE)
             ->setRequired('Musí být vybrána alespoň jedna organizace');

        $form->addText('NAZ', 'Název')
             ->setRequired('Musí být vyplněný "%label"')
             ->addRule(Form::MAX_LENGTH, 'Maximální délka pole "%label" je %d znaků.', 70);

        $form->addTextArea('POZNAMKA', 'Poznámka pro všechny ZP')
             ->setNullable();

        $form->addTextArea('UCINNA_LATKA', 'Učinná látka')
             ->setNullable();

        $form->addTextArea('BIOSIMOLAR', 'Biosimilar')
             ->setNullable();

        $form->addText('ATC', 'ATC skupina')
             ->addRule(Form::MAX_LENGTH, 'Maximální délka pole "%label" je %d znaků.', 7)
             ->setNullable();

        // Zjednodušené nastavení stavů pojišťoven
        foreach (\App\LekyModule\Presenters\ZjednodusenePresenter::POJISTOVNY as $pojistovna => $nazev) {
            $form->addSelect($pojistovna . '_STAV', $nazev . ' - Stav')
                 ->setItems(\App\LekyModule\Presenters\ZjednodusenePresenter::STAV)
                 ->addCondition(Form::EQUAL, 'Nasmlouváno')
                 ->toggle('nasmlouvano_' . $pojistovna)
                 ->endCondition();

            $form->addText($pojistovna . '_NASMLOUVANO_OD', $nazev . ' - Nasmlouváno od')
                 ->setHtmlAttribute('type', 'date')
                 ->setOption('id', 'nasmlouvano_' . $pojistovna)
                 ->setNullable();
        }
    }
}
