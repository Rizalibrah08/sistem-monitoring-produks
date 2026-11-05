<?php

namespace Database\Seeders;

use App\CriterionType;
use App\Models\Criterion;
use Illuminate\Database\Seeder;

class CriterionSeeder extends Seeder
{
    /**
     * Bobot hasil AHP konsensus Manajer dan RA (BR-25, CR 0,057) untuk setiap antrean.
     * C3 berjenis benefit pada antrean RA dan cost pada antrean lain (BR-10).
     * Hanya membuat kriteria yang belum ada, sehingga perubahan Admin tidak tertimpa.
     */
    public function run(): void
    {
        $criteria = [
            'C1' => ['Critical Ratio', 0.048, CriterionType::Cost],
            'C2' => ['Sisa hari kerja ke tenggat BPOM', 0.740, CriterionType::Cost],
            'C3' => ['Kelengkapan dokumen wajib', 0.106, CriterionType::Cost],
            'C4' => ['Umur tiket', 0.106, CriterionType::Benefit],
        ];

        foreach (Criterion::QUEUES as $queue) {
            foreach ($criteria as $code => [$name, $weight, $type]) {
                Criterion::query()->firstOrCreate(['queue' => $queue, 'code' => $code], [
                    'name' => $name,
                    'weight' => $weight,
                    'type' => $code === 'C3' && $queue === 'ra' ? CriterionType::Benefit : $type,
                ]);
            }
        }
    }
}
