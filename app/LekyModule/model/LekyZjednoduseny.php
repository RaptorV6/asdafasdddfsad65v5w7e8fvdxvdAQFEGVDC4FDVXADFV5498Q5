<?php
// app/LekyModule/model/LekyZjednoduseny.php

namespace App\LekyModule\Model;

class LekyZjednoduseny extends \App\Model\AModel {

    const LEKY_VIEW = "AKESO_LEKY_VIEW",
          POJISTOVNY_DG = "AKESO_LEKY_POJISTOVNY_DG",
          LEKY = "LEKY",
          AKESO_LEKY = "AKESO_LEKY";

    /**
     * ✅ ŘEŠENÍ PRO STARŠÍ SQL SERVER - vrací pole místo fluent
     */
    public function getDataSourceZjednodusene($organizace = null, $history = null) {
        // Nejdřív získáme všechna data
        $select = $this->db->select("*")
                ->from(self::LEKY_VIEW);

        if ($organizace) {
            $select->where("ORGANIZACE = %s", $organizace);
        }

        if (!$history) {
            $select->where("AKORD = 0");
        }

        $allData = $select->fetchAll();
        
        // Seskupíme podle názvu v PHP
        $uniqueLeky = [];
        foreach ($allData as $row) {
            $nazev = $row->NAZ;
            
            if (!isset($uniqueLeky[$nazev])) {
                $uniqueLeky[$nazev] = $row; // První výskyt
            }
        }
        
        // ✅ Vrátíme přímo pole dat místo fluent objektu
        return array_values($uniqueLeky);
    }

    /**
     * ✅ GLOBÁLNÍ vyhledávání - také vrací pole
     */
    public function getDataSourceWithGlobalSearch($searchTerm, $organizace = null, $history = null) {
        $select = $this->db->select("*")
                ->from(self::LEKY_VIEW);

        if ($searchTerm) {
            $select->where("(NAZ LIKE %~like~ OR ATC LIKE %~like~ OR UCINNA_LATKA LIKE %~like~ OR BIOSIMOLAR LIKE %~like~)",
                        $searchTerm, $searchTerm, $searchTerm, $searchTerm);
        }

        if ($organizace) {
            $select->where("ORGANIZACE = %s", $organizace);
        }

        if (!$history) {
            $select->where("AKORD = 0");
        }

        $allData = $select->fetchAll();
        
        // Seskupíme podle názvu
        $uniqueLeky = [];
        foreach ($allData as $row) {
            $nazev = $row->NAZ;
            if (!isset($uniqueLeky[$nazev])) {
                $uniqueLeky[$nazev] = $row;
            }
        }
        
        // ✅ Vrátíme přímo pole dat
        return array_values($uniqueLeky);
    }

    /**
     * DG detail podle ID léku (ne názvu)
     */
    public function getDataSource_DG_WithName($id_leku) {
        return $this->db->select('
            ROW_NUMBER() OVER (ORDER BY dg1.ID_LEKY + 1) AS ID,
            dg1.ID_LEKY,
            L.NAZ,
            dg1.ORGANIZACE,
            dg1.POJISTOVNA,
            dg1.DG_NAZEV,
            dg1.VILP,
            CONVERT(nvarchar(20), dg1.DG_PLATNOST_OD, 104) as DG_PLATNOST_OD,
            CONVERT(nvarchar(20), dg1.DG_PLATNOST_DO, 104) as DG_PLATNOST_DO')
            ->from(self::POJISTOVNY_DG)->as('dg1')
            ->leftJoin(self::LEKY_VIEW)->as('L')->on('dg1.ID_LEKY = L.ID_LEKY')
            ->where('dg1.ID_LEKY = %s and (dg1.DG_PLATNOST_DO >= getdate() or dg1.DG_PLATNOST_DO is null) and NOT (dg1.POJISTOVNA = 0 AND EXISTS (SELECT 1 FROM AKESO_LEKY_POJISTOVNY_DG dg2 WHERE dg2.ID_LEKY = dg1.ID_LEKY AND dg2.DG_NAZEV = dg1.DG_NAZEV AND dg2.POJISTOVNA != 0))', $id_leku)
            ->fetchAll();
    }

    /**
     * Najít první ID podle názvu
     */
    public function getFirstIdByName($nazev) {
        try {
            $results = $this->db->select('ID_LEKY')
                            ->from(self::LEKY_VIEW)
                            ->where('NAZ = %s', $nazev)
                            ->fetchAll();
            
            return !empty($results) ? $results[0]->ID_LEKY : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Kompatibilita - fallback na zjednodušenou metodu
     */
    public function getDataSource($organizace = null, $history = null) {
        return $this->getDataSourceZjednodusene($organizace, $history);
    }

    /**
     * Přidání DG záznamu
     */
    public function set_pojistovny_dg($values){
        return $this->db->insert(self::POJISTOVNY_DG, $values)->execute();
    }

    /**
     * Editace DG záznamu
     */
    public function set_pojistovny_dg_edit($values){
        return $this->db->update(self::POJISTOVNY_DG, [
            'VILP'=>$values['VILP'],
            'DG_PLATNOST_OD'=> $values['DG_PLATNOST_OD'],
            'DG_PLATNOST_DO'=> $values['DG_PLATNOST_DO']
        ])->where("ID_LEKY = %s and ORGANIZACE = %s and POJISTOVNA = %s and DG_NAZEV = %s", 
            $values['ID_LEKY'], 
            $values['ORGANIZACE'],
            $values['POJISTOVNA'], 
            $values['DG_NAZEV']
        )->execute();
    }

    /**
     * Kompatibilita - metoda pro vkládání léků
     */
    public function insertLeky($value) {
        return $this->db->query("MERGE INTO " . self::AKESO_LEKY . " as lek USING (SELECT ID_LEKY = %s) AS id_leky ON lek.ID_LEKY = id_leky.ID_LEKY WHEN MATCHED THEN UPDATE SET ID_LEKY = %s, NAZ = %s, POZNAMKA = %s, UCINNA_LATKA = %s, BIOSIMOLAR = %s, ORGANIZACE = (%s), ATC = %s WHEN NOT MATCHED THEN INSERT (ID_LEKY,NAZ,POZNAMKA,UCINNA_LATKA,BIOSIMOLAR,ORGANIZACE,ATC) VALUES(%s,%s,%s,%s,%s,(%s),%s); ", 
            $value->ID_LEKY, 
            $value->ID_LEKY, 
            $value->NAZ, 
            $value->POZNAMKA, 
            $value->UCINNA_LATKA, 
            $value->BIOSIMOLAR, 
            $value->ORGANIZACE, 
            $value->ATC, 
            $value->ID_LEKY, 
            $value->NAZ, 
            $value->POZNAMKA, 
            $value->UCINNA_LATKA, 
            $value->BIOSIMOLAR, 
            $value->ORGANIZACE, 
            $value->ATC);
    }
}