select * from rapporti r LEFT JOIN indirizzi i on r.id = i.id_rapporto JOIN ambiti a on r.id_ambito = a.id
WHERE r.fine is NULL AND r.id_tipologia = 1
  AND i.lat IS NULL AND a.descrizione LIKE '%MESSIN%'
