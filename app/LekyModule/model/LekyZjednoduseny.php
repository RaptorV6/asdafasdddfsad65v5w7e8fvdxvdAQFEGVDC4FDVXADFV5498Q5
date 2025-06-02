<?php
// app/LekyModule/model/LekyZjednoduseny.php

namespace App\LekyModule\Model;

class LekyZjednoduseny extends \App\Model\AModel {

    const LEKY_VIEW = "AKESO_LEKY_VIEW",
          POJISTOVNY_DG = "AKESO_LEKY_POJISTOVNY_DG",
          LEKY = "LEKY";

    /**
     * ✅ JEDNODUCHÉ ŘEŠENÍ - Načíst vše, seskupit v PHP, vrátit jako DibiResult
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
        
        // ✅ Vytvoříme nový jednoduchý dotaz s našimi daty
        $ids = [];
        foreach ($uniqueLeky as $row) {
            $ids[] = $row->ID_LEKY;
        }
        
        if (empty($ids)) {
            // Prázdný rezultát, ale stále DibiResult
            return $this->db->select("*")->from(self::LEKY_VIEW)->where("1=0");
        }
        
        // Vrátíme DibiResult s našimi vybranými ID
        return $this->db->select("*")
                       ->from(self::LEKY_VIEW)
                       ->where("ID_LEKY IN %in", $ids);
    }

    /**
     * ✅ JEDNODUCHÉ globální vyhledávání
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
        
        $ids = [];
        foreach ($uniqueLeky as $row) {
            $ids[] = $row->ID_LEKY;
        }
        
        if (empty($ids)) {
            return $this->db->select("*")->from(self::LEKY_VIEW)->where("1=0");
        }
        
        return $this->db->select("*")
                       ->from(self::LEKY_VIEW)
                       ->where("ID_LEKY IN %in", $ids);
    }

    /**
     * DG detail s názvem léku
     */
    public function getDataSource_DG_WithName($nazev_leku) {
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
            ->where('L.NAZ = %s and (dg1.DG_PLATNOST_DO >= getdate() or dg1.DG_PLATNOST_DO is null)', $nazev_leku)
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
}