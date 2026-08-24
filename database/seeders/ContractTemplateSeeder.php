<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\ContractTemplate;
use Illuminate\Database\Seeder;

class ContractTemplateSeeder extends Seeder
{
    public function run(): void
    {
        // EN
        $enHtml = <<<'HTML'
<h3 style="text-align:center; margin:0;">RESIDENTIAL LEASE AGREEMENT</h3>
<p style="text-align:center; margin:4px 0 12px 0;">(Standard Template)</p>

<p>
  This Residential Lease Agreement (“Agreement”) is made between <strong>{{landlord_name}}</strong> (“Landlord”)
  and <strong>{{tenant_full_name}}</strong> (“Tenant”).
</p>

<h4>1. Parties</h4>
<ul>
  <li><strong>Landlord:</strong> {{landlord_name}} | Phone: {{landlord_phone}} | Email: {{landlord_email}}</li>
  <li><strong>Tenant:</strong> {{tenant_full_name}} | Phone: {{tenant_phone}} | Email: {{tenant_email}} | ID: {{tenant_national_id}}</li>
</ul>

<h4>2. Premises</h4>
<p>
  The Landlord leases to the Tenant the residential unit identified as <strong>Unit {{unit_code}}</strong>,
  located at: <strong>{{property_address}}</strong> (“Premises”).
</p>

<h4>3. Term</h4>
<p>
  The lease term begins on <strong>{{lease_start_date}}</strong>
  and ends on <strong>{{lease_end_date}}</strong> (if applicable).
</p>

<h4>4. Rent</h4>
<p>
  Tenant agrees to pay rent of <strong>{{monthly_rent}} RWF</strong> per month.
  Rent is due monthly in advance on the agreed due date indicated by Landlord’s rent policy or written notice.
</p>

<h4>5. Security Deposit</h4>
<p>
  Tenant pays a security deposit of <strong>{{deposit}} RWF</strong>. The deposit may be used to cover unpaid rent,
  damages beyond normal wear and tear, missing items, cleaning beyond reasonable use, or other lawful deductions.
</p>

<h4>6. Utilities & Services</h4>
<p>
  Unless otherwise agreed in writing, Tenant is responsible for utilities and services used at the Premises,
  including but not limited to water, electricity, internet, and waste services.
</p>

<h4>7. Use and Occupancy</h4>
<ul>
  <li>The Premises shall be used for residential purposes only.</li>
  <li>Tenant shall not overcrowd the Premises and shall comply with applicable rules and regulations.</li>
  <li>No illegal activity is permitted.</li>
</ul>

<h4>8. Maintenance & Repairs</h4>
<ul>
  <li>Tenant shall keep the Premises clean and in good condition.</li>
  <li>Tenant must report maintenance issues promptly.</li>
  <li>Tenant is responsible for damage caused by Tenant, occupants, or guests.</li>
</ul>

<h4>9. Inspection & Inventory (Move-In / Move-Out)</h4>
<p>
  The parties may conduct move-in and move-out inspections and agree on the inventory and condition of items.
  Any missing or damaged items may be charged based on the inspection report and agreed deduction policy.
</p>

<h4>10. Late Payment</h4>
<p>
  Late fees may apply if rent is unpaid after the due date and applicable grace period, according to Landlord’s rent policy.
</p>

<h4>11. Subletting & Assignment</h4>
<p>
  Tenant may not sublet, assign, or transfer this Agreement without prior written consent from the Landlord.
</p>

<h4>12. Termination</h4>
<ul>
  <li>Either party may terminate according to the notice period required by law or written agreement.</li>
  <li>Upon termination, Tenant must return keys and vacate the Premises in good condition.</li>
</ul>

<h4>13. Governing Rules</h4>
<p>
  This Agreement shall be interpreted in accordance with applicable local laws and regulations.
</p>

<h4>14. Entire Agreement</h4>
<p>
  This document represents the entire agreement between the parties. Any changes must be in writing and signed by both parties.
</p>

<hr>

<h4>Signatures</h4>
<p>
  <strong>Landlord:</strong> {{landlord_name}} <br>
  Signature: ____________________________ Date: ______________________
</p>

<p>
  <strong>Tenant:</strong> {{tenant_full_name}} <br>
  Signature: ____________________________ Date: ______________________
</p>
HTML;

        Account::query()->each(fn (Account $account) => ContractTemplate::withoutGlobalScopes()->updateOrCreate(
            ['account_id' => $account->id, 'name' => 'Standard Residential Lease', 'language' => 'en', 'version' => '1.0'],
            ['is_active' => true, 'body_html' => $enHtml],
        ));

        // FR
        $frHtml = <<<'HTML'
<h3 style="text-align:center; margin:0;">CONTRAT DE LOCATION D’HABITATION</h3>
<p style="text-align:center; margin:4px 0 12px 0;">(Modèle Standard)</p>

<p>
  Le présent contrat (“Contrat”) est conclu entre <strong>{{landlord_name}}</strong> (“Bailleur”)
  et <strong>{{tenant_full_name}}</strong> (“Locataire”).
</p>

<h4>1. Parties</h4>
<ul>
  <li><strong>Bailleur :</strong> {{landlord_name}} | Tél : {{landlord_phone}} | Email : {{landlord_email}}</li>
  <li><strong>Locataire :</strong> {{tenant_full_name}} | Tél : {{tenant_phone}} | Email : {{tenant_email}} | ID : {{tenant_national_id}}</li>
</ul>

<h4>2. Logement</h4>
<p>
  Le Bailleur loue au Locataire le logement identifié comme <strong>Unité {{unit_code}}</strong>,
  situé à : <strong>{{property_address}}</strong> (“Logement”).
</p>

<h4>3. Durée</h4>
<p>
  La location commence le <strong>{{lease_start_date}}</strong>
  et se termine le <strong>{{lease_end_date}}</strong> (si applicable).
</p>

<h4>4. Loyer</h4>
<p>
  Le Locataire s’engage à payer un loyer mensuel de <strong>{{monthly_rent}} RWF</strong>.
  Le loyer est payable mensuellement d’avance selon la date d’échéance convenue ou la politique du Bailleur.
</p>

<h4>5. Dépôt de garantie</h4>
<p>
  Le Locataire verse un dépôt de garantie de <strong>{{deposit}} RWF</strong>. Ce dépôt peut être utilisé pour couvrir
  les loyers impayés, les dégradations au-delà de l’usure normale, les objets manquants, ou toute déduction légale.
</p>

<h4>6. Charges & Services</h4>
<p>
  Sauf accord écrit contraire, le Locataire prend en charge les consommations et services du Logement
  (eau, électricité, internet, déchets, etc.).
</p>

<h4>7. Usage du logement</h4>
<ul>
  <li>Le Logement est destiné uniquement à un usage d’habitation.</li>
  <li>Le Locataire respecte les règles applicables et évite toute sur-occupation.</li>
  <li>Toute activité illégale est interdite.</li>
</ul>

<h4>8. Entretien & Réparations</h4>
<ul>
  <li>Le Locataire maintient le Logement propre et en bon état.</li>
  <li>Tout incident ou panne doit être signalé rapidement.</li>
  <li>Le Locataire est responsable des dommages causés par lui-même, ses occupants ou invités.</li>
</ul>

<h4>9. État des lieux / Inventaire (Entrée / Sortie)</h4>
<p>
  Un état des lieux d’entrée et de sortie peut être réalisé, incluant l’inventaire et l’état des équipements.
  Les objets manquants ou endommagés peuvent entraîner des retenues selon le rapport d’inspection et la politique de déduction.
</p>

<h4>10. Retard de paiement</h4>
<p>
  Des pénalités peuvent s’appliquer en cas de retard après la date d’échéance et le délai de grâce,
  conformément à la politique du Bailleur.
</p>

<h4>11. Sous-location / Cession</h4>
<p>
  Toute sous-location ou cession est interdite sans accord écrit préalable du Bailleur.
</p>

<h4>12. Résiliation</h4>
<ul>
  <li>La résiliation se fait selon le préavis prévu par la loi ou accord écrit.</li>
  <li>À la fin du contrat, le Locataire restitue les clés et libère le Logement en bon état.</li>
</ul>

<h4>13. Droit applicable</h4>
<p>
  Le présent Contrat est soumis aux lois et règlements applicables.
</p>

<h4>14. Intégralité</h4>
<p>
  Le présent document constitue l’intégralité de l’accord. Toute modification doit être écrite et signée par les deux parties.
</p>

<hr>

<h4>Signatures</h4>
<p>
  <strong>Bailleur :</strong> {{landlord_name}} <br>
  Signature : ____________________________ Date : ______________________
</p>

<p>
  <strong>Locataire :</strong> {{tenant_full_name}} <br>
  Signature : ____________________________ Date : ______________________
</p>
HTML;

        Account::query()->each(fn (Account $account) => ContractTemplate::withoutGlobalScopes()->updateOrCreate(
            ['account_id' => $account->id, 'name' => 'Bail d’Habitation Standard', 'language' => 'fr', 'version' => '1.0'],
            ['is_active' => true, 'body_html' => $frHtml],
        ));

        // RW
        $rwHtml = <<<'HTML'
<h3 style="text-align:center; margin:0;">AMASEZERANO Y’UBUKODE BW’INZU</h3>
<p style="text-align:center; margin:4px 0 12px 0;">(Inyandiko y’icyitegererezo)</p>

<p>
  Aya masezerano (“Amasezerano”) agizwe hagati ya <strong>{{landlord_name}}</strong> (“Nyir’Inzu/Ukodesha”)
  na <strong>{{tenant_full_name}}</strong> (“Ukodesha/Umukode”).
</p>

<h4>1. Abayagiranye</h4>
<ul>
  <li><strong>Nyir’Inzu/Ukodesha :</strong> {{landlord_name}} | Tel: {{landlord_phone}} | Email: {{landlord_email}}</li>
  <li><strong>Umukode :</strong> {{tenant_full_name}} | Tel: {{tenant_phone}} | Email: {{tenant_email}} | ID: {{tenant_national_id}}</li>
</ul>

<h4>2. Inzu/Igice gikodeshwa</h4>
<p>
  Nyir’Inzu akodesha Umukode inzu/igice cy’inyubako cyitwa <strong>Unit {{unit_code}}</strong>,
  giherereye kuri: <strong>{{property_address}}</strong> (“Inzu”).
</p>

<h4>3. Igihe cy’Ubukode</h4>
<p>
  Ubukode butangira ku wa <strong>{{lease_start_date}}</strong>
  bukarangira ku wa <strong>{{lease_end_date}}</strong> (niba bwarashyizweho).
</p>

<h4>4. Ubukode (Amafaranga y’ukwezi)</h4>
<p>
  Umukode yemera kwishyura ubukode bwa <strong>{{monthly_rent}} RWF</strong> buri kwezi.
  Ubukode bwishyurwa buri kwezi hakiri kare ku itariki yemeranyijweho cyangwa hakurikijwe politiki ya Nyir’Inzu.
</p>

<h4>5. Ingwate (Deposit)</h4>
<p>
  Umukode atanga ingwate ya <strong>{{deposit}} RWF</strong>. Iyi ngwate ishobora gukoreshwa mu kwishyura
  ubukode butishyuwe, gusana ibyangiritse birenze ibisanzwe, ibikoresho/ibintu bibuze, isuku irenze ibisanzwe,
  cyangwa ibindi byemewe n’amategeko.
</p>

<h4>6. Amafaranga y’amazi, umuriro n’izindi serivisi</h4>
<p>
  Keretse habayeho andi masezerano yanditse, Umukode ni we wishyura amazi, umuriro, internet, imyanda n’izindi serivisi zikoreshwa mu nzu.
</p>

<h4>7. Ikoreshwa ry’Inzu</h4>
<ul>
  <li>Inzu igenewe guturwamo gusa.</li>
  <li>Umukode agomba kubahiriza amategeko n’amabwiriza kandi akirinda kuzuza abantu birenze ubushobozi bw’inzu.</li>
  <li>Ibikorwa binyuranyije n’amategeko birabujijwe.</li>
</ul>

<h4>8. Isuku, Kubungabunga no Gusana</h4>
<ul>
  <li>Umukode agomba kubungabunga isuku n’uko inzu imeze.</li>
  <li>Ibibazo byangiza inzu bigomba kumenyeshwa vuba.</li>
  <li>Umukode ashinzwe ibyangiritse byatewe na we, abo babana, cyangwa abashyitsi.</li>
</ul>

<h4>9. Igenzura n’ibarura (Move-In / Move-Out)</h4>
<p>
  Abimpande zombi zishobora gukora igenzura ryo kwinjira no gusohoka harimo ibarura n’uko ibikoresho bihagaze.
  Ibintu bibuze cyangwa byangiritse bishobora kwishyurwa hashingiwe kuri raporo y’igenzura na politiki yo gukata ku ngwate.
</p>

<h4>10. Gutinda kwishyura</h4>
<p>
  Hari igihe hashobora gukurikizwa amande yo gutinda kwishyura nyuma y’itariki yo kwishyura n’igihe cy’ubworoherane (grace period),
  hakurikijwe politiki ya Nyir’Inzu.
</p>

<h4>11. Gukodesha undi (Subletting) / Kwimura uburenganzira</h4>
<p>
  Umukode ntashobora gukodesha undi cyangwa kwimurira undi uburenganzira atabiherewe uburenganzira bwanditse na Nyir’Inzu.
</p>

<h4>12. Gusoza ubukode</h4>
<ul>
  <li>Gusoza ubukode bikorwa hakurikijwe itegeko cyangwa igihe cyo kumenyesha cyemeranyijweho mu nyandiko.</li>
  <li>Umukode agomba gusubiza imfunguzo no gusiga inzu imeze neza.</li>
</ul>

<h4>13. Amategeko agenga amasezerano</h4>
<p>
  Aya masezerano asobanurwa hakurikijwe amategeko n’amabwiriza bikurikizwa.
</p>

<h4>14. Amasezerano yose hamwe</h4>
<p>
  Iyi nyandiko ni yo masezerano yose hagati y’impande zombi. Icyahinduka cyose kigomba kuba cyanditse kandi kigashyirwaho umukono n’impande zombi.
</p>

<hr>

<h4>Imikono</h4>
<p>
  <strong>Nyir’Inzu/Ukodesha:</strong> {{landlord_name}} <br>
  Umukono: ____________________________ Itariki: ______________________
</p>

<p>
  <strong>Umukode:</strong> {{tenant_full_name}} <br>
  Umukono: ____________________________ Itariki: ______________________
</p>
HTML;

        Account::query()->each(fn (Account $account) => ContractTemplate::withoutGlobalScopes()->updateOrCreate(
            ['account_id' => $account->id, 'name' => 'Amasezerano y’Ubukode (Standard)', 'language' => 'rw', 'version' => '1.0'],
            ['is_active' => true, 'body_html' => $rwHtml],
        ));
    }
}
