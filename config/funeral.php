<?php

return [
    // Campi normalizzati condivisi fra scheda, documenti e manifesto.
    'sections' => [
        'Defunto' => [
            'last_name' => 'Cognome', 'first_name' => 'Nome', 'sex' => 'Sesso',
            'birth_place' => 'Luogo di nascita', 'birth_date' => 'Data di nascita',
            'father_name' => 'Paternità', 'mother_name' => 'Maternità',
            'residence_address' => 'Indirizzo di residenza', 'residence_city' => 'Comune di residenza',
            'marital_status' => 'Stato civile', 'spouse_name' => 'Nome e cognome coniuge', 'occupation' => 'Professione',
            'death_place' => 'Luogo del decesso', 'death_address' => 'Indirizzo del decesso',
            'death_date' => 'Data del decesso', 'death_time' => 'Ora del decesso',
            'death_cause' => 'Causa del decesso (riservata)', 'reference_phone' => 'Telefono di riferimento',
        ],
        'Familiare referente' => [
            'contact_first_name' => 'Nome referente', 'contact_last_name' => 'Cognome referente',
            'contact_relationship' => 'Parentela', 'contact_phone' => 'Telefono referente', 'contact_email' => 'Email referente',
            'contact_address' => 'Indirizzo referente', 'contact_city' => 'Comune referente',
        ],
        'Camera ardente e rito' => [
            'viewing_venue' => 'Sede camera ardente', 'viewing_address' => 'Indirizzo camera ardente',
            'viewing_starts_at' => 'Inizio esposizione', 'viewing_ends_at' => 'Fine esposizione',
            'church' => 'Chiesa / parrocchia', 'ceremony_city' => 'Località del rito', 'ceremony_at' => 'Data e ora esequie',
            'transfer_place' => 'Luogo del trasferimento', 'transfer_at' => 'Data e ora trasferimento',
            'cemetery' => 'Cimitero di destinazione', 'burial_type' => 'Tipo di sepoltura (facoltativo)',
        ],
        'Trasporto' => [
            'competent_city' => 'Comune competente', 'transport_origin' => 'Luogo di partenza',
            'transport_destination' => 'Destinazione trasporto', 'transport_route' => 'Percorso',
            'transport_at' => 'Data e ora trasporto', 'authorization_number' => 'Estremi autorizzazione',
        ],
        'Cremazione' => [
            'crematorium' => 'Crematorio', 'ashes_destination' => 'Destinazione delle ceneri',
        ],
    ],
    'safe_area' => ['left' => 27, 'top' => 6, 'width' => 69, 'height' => 88],
];
