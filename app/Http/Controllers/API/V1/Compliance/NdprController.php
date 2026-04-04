<?php

namespace App\Http\Controllers\API\V1\Compliance;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * NDPR 2023 Checklist
 * ────────────────────
 * GET  /api/v1/compliance/ndpr        — list all 11 items merged with saved state
 * PATCH /api/v1/compliance/ndpr/{id}  — update an item (implemented, evidence, owner, last_reviewed)
 */
class NdprController extends BaseController
{
    /** Canonical 11-item checklist (static definition) */
    private function defaultItems(): array
    {
        return [
            ['id' => 1, 'category' => 'lawful_basis',         'article' => 'Art. 2.2',  'title' => 'Lawful Basis for Processing',           'description' => 'Identify and document a lawful basis (consent, contract, legal obligation, vital interests, public task, or legitimate interests) for every processing activity.', 'mandatory' => true],
            ['id' => 2, 'category' => 'lawful_basis',         'article' => 'Art. 2.3',  'title' => 'Privacy Notice / Policy',               'description' => 'Publish a clear, plain-language privacy notice that describes what data is collected, why, how long it is kept, and who it is shared with.', 'mandatory' => true],
            ['id' => 3, 'category' => 'data_subject_rights',  'article' => 'Art. 3.1',  'title' => 'Right of Access',                       'description' => 'Provide a mechanism for data subjects to request and receive a copy of their personal data within 30 days.', 'mandatory' => true],
            ['id' => 4, 'category' => 'data_subject_rights',  'article' => 'Art. 3.3',  'title' => 'Right to Rectification & Erasure',      'description' => 'Allow data subjects to correct inaccurate data and to request erasure where processing is no longer lawful.', 'mandatory' => true],
            ['id' => 5, 'category' => 'data_subject_rights',  'article' => 'Art. 3.5',  'title' => 'Right to Object / Withdraw Consent',    'description' => 'Provide a simple mechanism to withdraw consent or object to processing at any time without detriment.', 'mandatory' => true],
            ['id' => 6, 'category' => 'security',             'article' => 'Art. 4.1',  'title' => 'Technical & Organisational Measures',   'description' => 'Implement appropriate technical (encryption, access controls, backups) and organisational (training, policies) security measures.', 'mandatory' => true],
            ['id' => 7, 'category' => 'security',             'article' => 'Art. 4.2',  'title' => 'Data Breach Notification',              'description' => 'Establish a process to detect, contain, and notify NITDA of a data breach within 72 hours of becoming aware. Notify affected subjects without undue delay.', 'mandatory' => true],
            ['id' => 8, 'category' => 'governance',           'article' => 'Art. 5.1',  'title' => 'Data Protection Officer (DPO)',         'description' => 'Designate a DPO if processing special-category health/mental-health data at scale, and register the DPO with NITDA.', 'mandatory' => true],
            ['id' => 9, 'category' => 'governance',           'article' => 'Art. 5.3',  'title' => 'Data Protection Impact Assessment',     'description' => 'Conduct and document a DPIA for any high-risk processing (e.g., mental health profiling, AI-based recommendations).', 'mandatory' => false],
            ['id' => 10,'category' => 'governance',           'article' => 'Art. 5.4',  'title' => 'Record of Processing Activities (RoPA)','description' => 'Maintain an internal register of all processing activities including purpose, legal basis, data categories, retention periods, and third-party transfers.', 'mandatory' => false],
            ['id' => 11,'category' => 'transfer',             'article' => 'Art. 6.1',  'title' => 'Cross-Border Transfer Safeguards',      'description' => 'Ensure personal data transferred outside Nigeria is protected to NDPR standards (adequacy decision, SCCs, or explicit consent).', 'mandatory' => false],
        ];
    }

    public function index()
    {
        $defaults  = $this->defaultItems();
        $tableExists = Schema::hasTable('ndpr_checklist');

        if (! $tableExists) {
            // Return defaults with empty state
            $items = array_map(fn ($item) => array_merge($item, [
                'implemented'   => false,
                'evidence'      => null,
                'owner'         => null,
                'last_reviewed' => null,
            ]), $defaults);

            return $this->sendResponse($items, 'NDPR checklist retrieved (no saved state yet).');
        }

        $saved = DB::table('ndpr_checklist')
            ->get()
            ->keyBy('item_id');

        $items = array_map(function ($item) use ($saved) {
            $state = $saved->get($item['id']);
            return array_merge($item, [
                'implemented'   => $state ? (bool) $state->implemented : false,
                'evidence'      => $state->evidence      ?? null,
                'owner'         => $state->owner         ?? null,
                'last_reviewed' => $state->last_reviewed ?? null,
            ]);
        }, $defaults);

        return $this->sendResponse($items, 'NDPR checklist retrieved.');
    }

    public function update(Request $request, int $id)
    {
        $validIds = array_column($this->defaultItems(), 'id');
        if (! in_array($id, $validIds)) {
            return $this->sendError('NDPR item not found.', [], 404);
        }

        $data = $request->validate([
            'implemented'   => 'sometimes|boolean',
            'evidence'      => 'sometimes|nullable|string|max:2000',
            'owner'         => 'sometimes|nullable|string|max:255',
            'last_reviewed' => 'sometimes|nullable|date',
        ]);

        // Create table if it does not yet exist (Year 1 bootstrap — avoid needing a migration run)
        if (! Schema::hasTable('ndpr_checklist')) {
            DB::statement('
                CREATE TABLE ndpr_checklist (
                    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    item_id      TINYINT UNSIGNED NOT NULL UNIQUE,
                    implemented  TINYINT(1) NOT NULL DEFAULT 0,
                    evidence     TEXT NULL,
                    owner        VARCHAR(255) NULL,
                    last_reviewed DATE NULL,
                    updated_by   BIGINT UNSIGNED NULL,
                    created_at   TIMESTAMP NULL,
                    updated_at   TIMESTAMP NULL
                )
            ');
        }

        $payload = array_merge($data, [
            'item_id'    => $id,
            'updated_by' => $request->user()?->id,
            'updated_at' => now(),
        ]);

        $exists = DB::table('ndpr_checklist')->where('item_id', $id)->exists();

        if ($exists) {
            DB::table('ndpr_checklist')->where('item_id', $id)->update($payload);
        } else {
            DB::table('ndpr_checklist')->insert(array_merge($payload, ['created_at' => now()]));
        }

        return $this->sendResponse(['id' => $id], 'NDPR item updated.');
    }
}
