<?php

namespace App\Support;

/**
 * Listes de référence proposées aux mutuelles (importables au choix).
 *
 * Inspirées des nomenclatures et paniers de soins couramment utilisés par les
 * mutuelles de santé d'Afrique de l'Ouest et centrale (soins de santé primaires,
 * médicaments essentiels génériques, formules de garanties à taux de prise en
 * charge de 70 à 80 % avec ticket modérateur). Ce sont des MODÈLES :
 *  - aucun tarif officiel n'est inventé — les tarifs conventionnés et les
 *    cotisations sont saisis par la mutuelle ;
 *  - les taux et stages d'attente proposés sont modifiables avant import.
 */
class ReferenceCatalog
{
    /** Actes : code => [libellé, catégorie] */
    public const ACTS = [
        // Consultations
        'CS-GEN'   => ['Consultation de médecine générale', 'Consultations'],
        'CS-INF'   => ['Consultation infirmière (centre / poste de santé)', 'Consultations'],
        'CS-SF'    => ['Consultation sage-femme', 'Consultations'],
        'CS-SPE'   => ['Consultation de spécialiste', 'Consultations'],
        'CS-PED'   => ['Consultation pédiatrique', 'Consultations'],
        'CS-URG'   => ['Consultation d’urgence', 'Consultations'],
        'CS-NUIT'  => ['Consultation de nuit / jour férié', 'Consultations'],
        // Maternité et santé de l'enfant
        'MAT-CPN'  => ['Consultation prénatale (CPN)', 'Maternité'],
        'MAT-CPON' => ['Consultation postnatale', 'Maternité'],
        'MAT-ACC'  => ['Accouchement simple (voie basse)', 'Maternité'],
        'MAT-DYS'  => ['Accouchement dystocique', 'Maternité'],
        'MAT-CES'  => ['Césarienne', 'Maternité'],
        'MAT-PF'   => ['Planification familiale', 'Maternité'],
        'ENF-CPS'  => ['Consultation préventive de l’enfant (CPS)', 'Maternité'],
        // Hospitalisation
        'HOS-OBS'  => ['Mise en observation (moins de 24 h)', 'Hospitalisation'],
        'HOS-JMED' => ['Journée d’hospitalisation — médecine', 'Hospitalisation'],
        'HOS-JCHI' => ['Journée d’hospitalisation — chirurgie', 'Hospitalisation'],
        'HOS-REA'  => ['Journée de réanimation / soins intensifs', 'Hospitalisation'],
        // Laboratoire
        'LAB-TDR'  => ['Test de diagnostic rapide du paludisme (TDR)', 'Analyses de laboratoire'],
        'LAB-GE'   => ['Goutte épaisse / frottis sanguin', 'Analyses de laboratoire'],
        'LAB-NFS'  => ['Numération formule sanguine (NFS)', 'Analyses de laboratoire'],
        'LAB-GLY'  => ['Glycémie', 'Analyses de laboratoire'],
        'LAB-CREA' => ['Créatininémie', 'Analyses de laboratoire'],
        'LAB-TRANS'=> ['Transaminases (ASAT / ALAT)', 'Analyses de laboratoire'],
        'LAB-WID'  => ['Sérodiagnostic de Widal', 'Analyses de laboratoire'],
        'LAB-ECBU' => ['Examen cytobactériologique des urines (ECBU)', 'Analyses de laboratoire'],
        'LAB-SELL' => ['Examen parasitologique des selles', 'Analyses de laboratoire'],
        'LAB-GSRH' => ['Groupe sanguin et rhésus', 'Analyses de laboratoire'],
        'LAB-VIH'  => ['Dépistage VIH (test rapide)', 'Analyses de laboratoire'],
        'LAB-HB'   => ['Hémoglobine / taux d’hématocrite', 'Analyses de laboratoire'],
        'LAB-BHCG' => ['Test de grossesse (β-HCG)', 'Analyses de laboratoire'],
        // Imagerie
        'IMG-RX'   => ['Radiographie standard', 'Imagerie médicale'],
        'IMG-ECHO' => ['Échographie abdominale / pelvienne', 'Imagerie médicale'],
        'IMG-ECHOB'=> ['Échographie obstétricale', 'Imagerie médicale'],
        'IMG-ECG'  => ['Électrocardiogramme (ECG)', 'Imagerie médicale'],
        'IMG-SCAN' => ['Scanner (tomodensitométrie)', 'Imagerie médicale'],
        // Chirurgie et soins
        'CHI-PET'  => ['Petite chirurgie (suture, incision, pansement chirurgical)', 'Chirurgie'],
        'CHI-INT'  => ['Chirurgie intermédiaire (hernie, appendicite…)', 'Chirurgie'],
        'CHI-MAJ'  => ['Chirurgie majeure', 'Chirurgie'],
        'SOI-INJ'  => ['Injection / perfusion', 'Soins infirmiers'],
        'SOI-PANS' => ['Pansement simple', 'Soins infirmiers'],
        // Dentaire et optique
        'DEN-CS'   => ['Consultation dentaire', 'Soins dentaires'],
        'DEN-EXT'  => ['Extraction dentaire', 'Soins dentaires'],
        'DEN-SOIN' => ['Soin dentaire (obturation, détartrage)', 'Soins dentaires'],
        'OPH-CS'   => ['Consultation ophtalmologique', 'Optique'],
        'OPH-LUN'  => ['Verres correcteurs / lunettes', 'Optique'],
        // Pharmacie et divers
        'PHA-MED'  => ['Médicaments sur ordonnance', 'Pharmacie'],
        'KIN-SEA'  => ['Séance de kinésithérapie', 'Rééducation'],
        'EVA-AMB'  => ['Évacuation sanitaire / ambulance', 'Transport sanitaire'],
    ];

    /** Médicaments essentiels : code => [libellé, forme et conditionnement, catégorie MX-CONNECT] */
    public const MEDICINES = [
        'PARA-500'  => ['Paracétamol 500 mg', 'Comprimé, boîte de 20', 'antalgique'],
        'PARA-SIR'  => ['Paracétamol 120 mg/5 ml', 'Sirop, flacon de 100 ml', 'antalgique'],
        'PARA-INJ'  => ['Paracétamol 1 g injectable', 'Flacon perfusion 100 ml', 'antalgique'],
        'IBU-400'   => ['Ibuprofène 400 mg', 'Comprimé, boîte de 20', 'anti_inflammatoire'],
        'DICLO-50'  => ['Diclofénac 50 mg', 'Comprimé, boîte de 20', 'anti_inflammatoire'],
        'AAS-500'   => ['Acide acétylsalicylique 500 mg', 'Comprimé, boîte de 20', 'antalgique'],
        'AMOX-500'  => ['Amoxicilline 500 mg', 'Gélule, boîte de 12', 'antibiotique'],
        'AMOX-SIR'  => ['Amoxicilline 250 mg/5 ml', 'Poudre pour suspension buvable, 60 ml', 'antibiotique'],
        'AMOXC-1G'  => ['Amoxicilline + acide clavulanique 1 g', 'Comprimé, boîte de 14', 'antibiotique'],
        'COTRI-480' => ['Cotrimoxazole 400/80 mg', 'Comprimé, boîte de 20', 'antibiotique'],
        'CIPRO-500' => ['Ciprofloxacine 500 mg', 'Comprimé, boîte de 10', 'antibiotique'],
        'METRO-250' => ['Métronidazole 250 mg', 'Comprimé, boîte de 20', 'antiparasitaire'],
        'METRO-INJ' => ['Métronidazole 500 mg injectable', 'Flacon perfusion 100 ml', 'antiparasitaire'],
        'CEFTR-1G'  => ['Ceftriaxone 1 g injectable', 'Flacon + solvant', 'antibiotique'],
        'GENTA-80'  => ['Gentamicine 80 mg injectable', 'Ampoule 2 ml', 'antibiotique'],
        'DOXY-100'  => ['Doxycycline 100 mg', 'Comprimé, boîte de 10', 'antibiotique'],
        'ERY-500'   => ['Érythromycine 500 mg', 'Comprimé, boîte de 20', 'antibiotique'],
        'AL-2012'   => ['Artéméther + luméfantrine 20/120 mg', 'Comprimé, boîte de 24', 'antipaludique'],
        'ASAQ-ADU'  => ['Artésunate + amodiaquine (adulte)', 'Comprimé, boîte de 6', 'antipaludique'],
        'ARTES-60'  => ['Artésunate 60 mg injectable', 'Flacon + solvant', 'antipaludique'],
        'QUIN-INJ'  => ['Quinine 400 mg injectable', 'Ampoule 4 ml', 'antipaludique'],
        'SP-TPI'    => ['Sulfadoxine + pyriméthamine (TPI)', 'Comprimé, boîte de 3', 'antipaludique'],
        'ALB-400'   => ['Albendazole 400 mg', 'Comprimé, boîte de 1', 'antiparasitaire'],
        'MEBEN-100' => ['Mébendazole 100 mg', 'Comprimé, boîte de 6', 'antiparasitaire'],
        'PRAZI-600' => ['Praziquantel 600 mg', 'Comprimé, boîte de 4', 'antiparasitaire'],
        'SRO'       => ['Sels de réhydratation orale (SRO)', 'Sachet', 'supplement'],
        'ZINC-20'   => ['Zinc 20 mg', 'Comprimé dispersible, boîte de 10', 'supplement'],
        'FER-FOL'   => ['Fer + acide folique', 'Comprimé, boîte de 30', 'supplement'],
        'VIT-A'     => ['Vitamine A 200 000 UI', 'Capsule', 'supplement'],
        'MULTIVIT'  => ['Multivitamines', 'Comprimé, boîte de 30', 'supplement'],
        'AMLO-5'    => ['Amlodipine 5 mg', 'Comprimé, boîte de 30', 'antihypertenseur'],
        'HCTZ-25'   => ['Hydrochlorothiazide 25 mg', 'Comprimé, boîte de 30', 'antihypertenseur'],
        'CAPTO-25'  => ['Captopril 25 mg', 'Comprimé, boîte de 30', 'antihypertenseur'],
        'METF-500'  => ['Metformine 500 mg', 'Comprimé, boîte de 30', 'antidiabetique'],
        'GLIB-5'    => ['Glibenclamide 5 mg', 'Comprimé, boîte de 30', 'antidiabetique'],
        'INSU-NPH'  => ['Insuline NPH 100 UI/ml', 'Flacon 10 ml', 'antidiabetique'],
        'SALB-INH'  => ['Salbutamol 100 µg aérosol', 'Flacon pressurisé, 200 doses', 'autre'],
        'PRED-5'    => ['Prednisolone 5 mg', 'Comprimé, boîte de 20', 'anti_inflammatoire'],
        'OMEP-20'   => ['Oméprazole 20 mg', 'Gélule, boîte de 14', 'autre'],
        'HYOS-10'   => ['Butylscopolamine 10 mg', 'Comprimé, boîte de 20', 'antalgique'],
        'CHLORPH-4' => ['Chlorphéniramine 4 mg', 'Comprimé, boîte de 20', 'autre'],
        'OCYTO-10'  => ['Ocytocine 10 UI injectable', 'Ampoule 1 ml', 'autre'],
        'MISO-200'  => ['Misoprostol 200 µg', 'Comprimé, boîte de 3', 'autre'],
        'MGSO4'     => ['Sulfate de magnésium 50 % injectable', 'Ampoule 10 ml', 'autre'],
        'RL-500'    => ['Ringer lactate', 'Poche perfusion 500 ml', 'materiel'],
        'SG-5'      => ['Sérum glucosé 5 %', 'Poche perfusion 500 ml', 'materiel'],
        'SSI-09'    => ['Sérum salé isotonique 0,9 %', 'Poche perfusion 500 ml', 'materiel'],
        'DIAZ-10'   => ['Diazépam 10 mg injectable', 'Ampoule 2 ml', 'autre'],
        'FLUC-150'  => ['Fluconazole 150 mg', 'Gélule, boîte de 1', 'autre'],
        'MICO-CR'   => ['Miconazole 2 % crème', 'Tube 30 g', 'autre'],
        'TETRA-POM' => ['Tétracycline 1 % pommade ophtalmique', 'Tube 5 g', 'antibiotique'],
        'POVI-10'   => ['Povidone iodée 10 %', 'Flacon 125 ml', 'materiel'],
        'SER-5ML'   => ['Seringue 5 ml', 'Unité', 'materiel'],
        'GANTS'     => ['Gants d’examen', 'Boîte de 100', 'materiel'],
        'TDF3TCDTG' => ['Ténofovir + lamivudine + dolutégravir', 'Comprimé, boîte de 30', 'arv'],
    ];

    /**
     * Formules de garanties types : code => [nom, description, taux de prise en charge,
     * ticket modérateur, stage d'attente (jours), périodicité].
     */
    public const GUARANTEES = [
        'F-ESSENTIELLE' => ['Formule Essentielle', 'Soins de santé primaires : consultations en centre de santé, médicaments essentiels génériques, analyses courantes (paludisme, NFS, glycémie), accouchement simple.', 70, 30, 30, 'monthly'],
        'F-MAMAN-ENFANT'=> ['Formule Maman-Enfant', 'Suivi de grossesse (CPN), accouchement simple ou compliqué, césarienne, consultations préventives et soins des enfants de moins de 5 ans.', 80, 20, 90, 'monthly'],
        'F-FAMILLE'     => ['Formule Famille', 'Soins primaires + hospitalisation, petite et moyenne chirurgie, imagerie de base, pour l’adhérent et ses ayants droit.', 75, 25, 60, 'monthly'],
        'F-PLUS'        => ['Formule Plus', 'Formule Famille + consultations de spécialistes, chirurgie majeure, soins dentaires et optiques, évacuation sanitaire.', 80, 20, 90, 'quarterly'],
        'F-SCOLAIRE'    => ['Formule Scolaire', 'Élèves et étudiants : consultations, médicaments essentiels, petits soins et urgences pendant l’année scolaire.', 70, 30, 30, 'annual'],
    ];
}
