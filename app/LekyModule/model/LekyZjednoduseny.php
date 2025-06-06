<?php

namespace App\LekyModule\Model;

class LekyZjednoduseny extends \App\Model\AModel {

    const LEKY_VIEW = "AKESO_LEKY_VIEW",
          LEKY_EDIT = "AKESO_LEKY_EDIT",
          POJISTOVNY = "AKESO_LEKY_POJISTOVNY",
          POJISTOVNY_DG = "AKESO_LEKY_POJISTOVNY_DG",
          LEKY = "LEKY",
          AKESO_LEKY = "AKESO_LEKY";


    public function getDataSourceZjednodusene($organizace = null, $history = null) {
        $select = $this->db->select("*")->from(self::LEKY_VIEW);
        
        if ($organizace) {
            $select->where("ORGANIZACE = %s", $organizace);
        }

        if (!$history) {
            $select->where("AKORD = 0");
        }

        return $select;
    }

 
    public function getDataSourceGrouped($organizace = null, $history = null) {
        $select = $this->db->query('
            SELECT 
                CASE
                    WHEN COUNT(*) > 1
                    THEN NAZ + \' (\' + CAST(COUNT(*) AS VARCHAR) + \'x)\'
                    ELSE NAZ
                END as NAZ,
                ORGANIZACE,
                MAX(POZNAMKA) as POZNAMKA,
                MAX(UCINNA_LATKA) as UCINNA_LATKA,
                MAX(BIOSIMOLAR) as BIOSIMOLAR,
                MAX(ATC) as ATC,
                COUNT(*) as VARIANT_COUNT,
                MIN(ID_LEKY) as ID_LEKY,
                
                MAX([111_STAV]) as [111_STAV],
                MAX([111_NASMLOUVANO_OD]) as [111_NASMLOUVANO_OD],
                MAX([111_POZNAMKA]) as [111_POZNAMKA],
                MAX(poj111_BARVA) as poj111_BARVA,
                
                MAX([201_STAV]) as [201_STAV],
                MAX([201_NASMLOUVANO_OD]) as [201_NASMLOUVANO_OD],
                MAX([201_POZNAMKA]) as [201_POZNAMKA],
                MAX(poj201_BARVA) as poj201_BARVA,
                
                MAX([205_STAV]) as [205_STAV],
                MAX(poj205_BARVA) as poj205_BARVA,
                MAX([207_STAV]) as [207_STAV],
                MAX(poj207_BARVA) as poj207_BARVA,
                MAX([209_STAV]) as [209_STAV],
                MAX(poj209_BARVA) as poj209_BARVA,
                MAX([211_STAV]) as [211_STAV],
                MAX(poj211_BARVA) as poj211_BARVA,
                MAX([213_STAV]) as [213_STAV],
                MAX(poj213_BARVA) as poj213_BARVA
                
            FROM %n 
            WHERE AKORD = 0 %if', self::LEKY_VIEW, $organizace, 'AND ORGANIZACE = %s %end', $organizace, '
            GROUP BY NAZ, ORGANIZACE
        ');
        
        return $select->fetchAll();
    }


public function getDataSource_DG(string $id_leku) {
    return $this->db->select('ROW_NUMBER() OVER (ORDER BY ID_LEKY, POJISTOVNA) AS ID,[ID_LEKY],[ORGANIZACE],[POJISTOVNA],[DG_NAZEV],[VILP], CONVERT(nvarchar(20), DG_PLATNOST_OD, 104) as DG_PLATNOST_OD, CONVERT(nvarchar(20), DG_PLATNOST_DO, 104) as DG_PLATNOST_DO')
                    ->from(self::POJISTOVNY_DG)->as('dg1')
                    ->where('ID_LEKY = %s and (dg1.DG_PLATNOST_DO >= getdate() or dg1.DG_PLATNOST_DO is null) and NOT (dg1.POJISTOVNA = 0 AND EXISTS (SELECT 1 FROM AKESO_LEKY_POJISTOVNY_DG dg2 WHERE dg2.ID_LEKY = dg1.ID_LEKY AND dg2.DG_NAZEV = dg1.DG_NAZEV AND dg2.POJISTOVNA != 0))', $id_leku)->fetchAll();
}

    public function getDataSource($organizace = null, $history = null) {
        return $this->getDataSourceZjednodusene($organizace, $history);
    }

    public function getLeky($id) {
        return $this->db->select("*")
                        ->from(self::LEKY_EDIT)
                        ->where('ID_LEKY = %s', $id)
                        ->orderBy('ID_LEKY')
                        ->fetch();
    }

    public function getPojistovny($id, $org) {
        return $this->db->select("*")
                        ->from(self::POJISTOVNY)
                        ->where('ID_LEKY = %s and ORGANIZACE = %s', $id, $org)
                        ->orderBy('ID_LEKY')
                        ->fetchAssoc('POJISTOVNA');
    }

    public function getPojistovny_DG($id, $org, $poj) {
        return $this->db->select("*")
                        ->from(self::POJISTOVNY_DG)
                        ->where('ID_LEKY = %s and ORGANIZACE = %s and POJISTOVNA = %i and (DG_PLATNOST_DO >= getdate() or DG_PLATNOST_DO is null)', $id, $org, $poj)
                        ->orderBy('ID_LEKY')
                        ->fetchAll();
    }

    public function getDg($values) {
        return $this->db->select('KOD_SKUP')
                        ->from(\App\CiselnikyModule\Presenters\DgPresenter::DG)
                        ->where('KOD_SKUP like %s', $values . '%')
                        ->fetchPairs('KOD_SKUP', 'KOD_SKUP');
    }

    public function insert_edit_pojistovny($value) {
        $value->VILP_PLATNOST_OD = $value->VILP_PLATNOST_OD ?? null;
        $value->VILP_PLATNOST_DO = $value->VILP_PLATNOST_DO ?? null;
        $this->db->query("MERGE INTO " . self::POJISTOVNY . " as poj USING (SELECT ID_LEKY = %s, ORGANIZACE = %s, POJISTOVNA = %i) AS spoj ON poj.ID_LEKY = spoj.ID_LEKY AND poj.ORGANIZACE = spoj.ORGANIZACE AND poj.POJISTOVNA = spoj.POJISTOVNA WHEN MATCHED THEN UPDATE SET ID_LEKY = %s, NASMLOUVANO_OD = %d, ORGANIZACE = %s, POJISTOVNA =%i, STAV = %s, RL = %s, SMLOUVA = %s, POZNAMKA = %s WHEN NOT MATCHED THEN INSERT (ID_LEKY, NASMLOUVANO_OD, ORGANIZACE, POJISTOVNA, STAV, RL, SMLOUVA, POZNAMKA) VALUES(%s,%d,%s,%i,%s,%s,%s,%s); ", $value->ID_LEKY, $value->ORGANIZACE, $value->POJISTOVNA, $value->ID_LEKY, $value->NASMLOUVANO_OD, $value->ORGANIZACE, $value->POJISTOVNA, $value->STAV, $value->RL, $value->SMLOUVA, $value->POZNAMKA, $value->ID_LEKY, $value->NASMLOUVANO_OD, $value->ORGANIZACE, $value->POJISTOVNA, $value->STAV, $value->RL, $value->SMLOUVA, $value->POZNAMKA);
    }

    public function insertLeky($value) {
        return $this->db->query("MERGE INTO " . self::AKESO_LEKY . " as lek USING (SELECT ID_LEKY = %s) AS id_leky ON lek.ID_LEKY = id_leky.ID_LEKY WHEN MATCHED THEN UPDATE SET ID_LEKY = %s, NAZ = %s, DOP = %s, SILA = %s, BALENI = %s, POZNAMKA = %s, UCINNA_LATKA = %s, BIOSIMOLAR = %s, ORGANIZACE = (%s), ATC = %s, ATC3 = %s, UHR1 = %f, UHR2 = %f, UHR3 = %f, CENA_FAKTURACE = %f, CENA_MAX = %f,  CENA_VYROBCE_BEZDPH = %f, CENA_SENIMED_BEZDPH = %f, CENA_MUS_PHARMA = %f, CENA_MUS_NC_BEZDPH = %f, CENA_MUS_NC = %f, UHRADA = %f, KOMPENZACE = %f, BONUS = %f WHEN NOT MATCHED THEN INSERT (ID_LEKY,NAZ,DOP,SILA,BALENI,POZNAMKA,UCINNA_LATKA,BIOSIMOLAR,ORGANIZACE,ATC,ATC3,UHR1,UHR2,UHR3,CENA_FAKTURACE,CENA_MAX, CENA_VYROBCE_BEZDPH, CENA_SENIMED_BEZDPH, CENA_MUS_PHARMA, CENA_MUS_NC_BEZDPH, CENA_MUS_NC, UHRADA, KOMPENZACE, BONUS) VALUES(%s,%s,%s,%s,%s,%s,%s,%s,(%s),%s,%s,%f,%f,%f,%f,%f,%f,%f,%f,%f,%f,%f,%f,%f); ", $value->ID_LEKY, $value->ID_LEKY, $value->NAZ, $value->DOP, $value->SILA, $value->BALENI, $value->POZNAMKA, $value->UCINNA_LATKA, $value->BIOSIMOLAR, $value->ORGANIZACE, $value->ATC, $value->ATC3, $value->UHR1, $value->UHR2, $value->UHR3, $value->CENA_FAKTURACE, $value->CENA_MAX, $value->CENA_VYROBCE_BEZDPH, $value->CENA_SENIMED_BEZDPH, $value->CENA_MUS_PHARMA, $value->CENA_MUS_NC_BEZDPH, $value->CENA_MUS_NC, $value->UHRADA, $value->KOMPENZACE, $value->BONUS, $value->ID_LEKY, $value->NAZ, $value->DOP, $value->SILA, $value->BALENI, $value->POZNAMKA, $value->UCINNA_LATKA, $value->BIOSIMOLAR, $value->ORGANIZACE, $value->ATC, $value->ATC3, $value->UHR1, $value->UHR2, $value->UHR3, $value->CENA_FAKTURACE, $value->CENA_MAX, $value->CENA_VYROBCE_BEZDPH, $value->CENA_SENIMED_BEZDPH, $value->CENA_MUS_PHARMA, $value->CENA_MUS_NC_BEZDPH, $value->CENA_MUS_NC, $value->UHRADA, $value->KOMPENZACE, $value->BONUS);
    }

    public function insert_edit_pojistovny_dg($dg) {
        return $this->db->query("MERGE INTO " . self::POJISTOVNY_DG . " as poj USING (SELECT ID_LEKY = %s, ORGANIZACE = %s, POJISTOVNA = %i, DG_NAZEV = %s) AS spoj ON poj.ID_LEKY = spoj.ID_LEKY AND poj.ORGANIZACE = spoj.ORGANIZACE AND poj.POJISTOVNA = spoj.POJISTOVNA AND poj.DG_NAZEV = spoj.DG_NAZEV WHEN MATCHED THEN UPDATE SET ID_LEKY = %s, ORGANIZACE = %s, POJISTOVNA = %i, DG_NAZEV = %s, VILP = %b, DG_PLATNOST_OD = %d, DG_PLATNOST_DO = %d WHEN NOT MATCHED THEN INSERT (ID_LEKY, ORGANIZACE, POJISTOVNA, DG_NAZEV, VILP, DG_PLATNOST_OD, DG_PLATNOST_DO) VALUES(%s,%s,%i,%s,%b,%d,%d);", $dg->ID_LEKY, $dg->ORGANIZACE, $dg->POJISTOVNA, $dg->DG_NAZEV, $dg->ID_LEKY, $dg->ORGANIZACE, $dg->POJISTOVNA, $dg->DG_NAZEV, $dg->VILP, $dg->DG_PLATNOST_OD, $dg->DG_PLATNOST_DO, $dg->ID_LEKY, $dg->ORGANIZACE, $dg->POJISTOVNA, $dg->DG_NAZEV, $dg->VILP, $dg->DG_PLATNOST_OD, $dg->DG_PLATNOST_DO);
    }

    public function set_pojistovny_dg($values){ 
        return $this->db->insert(self::POJISTOVNY_DG, $values)->execute(); 
    }
    
// V LekyZjednoduseny.php
public function set_pojistovny_dg_edit($values){ 
    error_log("=== MODEL SET_POJISTOVNY_DG_EDIT ===");
    error_log("INPUT VALUES: " . print_r($values, true));
    
    // ✅ Připravit data pro update
    $updateData = [
        'VILP' => isset($values['VILP']) ? (int)$values['VILP'] : 0,
        'DG_PLATNOST_OD' => $values['DG_PLATNOST_OD'] ?: null, 
        'DG_PLATNOST_DO' => $values['DG_PLATNOST_DO'] ?: null
    ];
    
    error_log("UPDATE DATA: " . print_r($updateData, true));
    
    try {
        // ✅ Jednoduchý UPDATE dotaz bez OFFSET problémů
        $result = $this->db->update(self::POJISTOVNY_DG, $updateData)
            ->where(
                "ID_LEKY = %s AND ORGANIZACE = %s AND POJISTOVNA = %s AND DG_NAZEV = %s", 
                $values['ID_LEKY'], 
                $values['ORGANIZACE'],
                $values['POJISTOVNA'], 
                $values['DG_NAZEV']
            )->execute();
            
        error_log("DB UPDATE RESULT: $result");
        return $result;
        
    } catch (\Exception $e) {
        error_log("MODEL UPDATE ERROR: " . $e->getMessage());
        error_log("ERROR TRACE: " . $e->getTraceAsString());
        throw $e;
    }
}

    public function unset_pojistovny_dg($values){
    return $this->db->delete(self::POJISTOVNY_DG)
                    ->where("ID_LEKY = %s and ORGANIZACE = %s and POJISTOVNA = %s", 
                            $values['ID_LEKY'], $values['ORGANIZACE'], $values['POJISTOVNA'])
                    ->execute();
}
}