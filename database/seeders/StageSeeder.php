<?php

namespace Database\Seeders;

use App\Models\Stage;
use App\StagePhase;
use Illuminate\Database\Seeder;

class StageSeeder extends Seeder
{
    /**
     * Tahapan awal dengan SLA hari kerja dari BR-08 (batas atas usulan RA).
     * Tahap yang menunggu BPOM atau pihak luar tidak memakai SLA (FR-35, FR-91).
     * Hanya membuat tahap yang belum ada, sehingga perubahan Admin tidak tertimpa.
     */
    public function run(): void
    {
        $stages = [
            ['pengembangan_sampel', 'Pengembangan sampel', StagePhase::Initial, 14, false],
            ['kontrak', 'Kontrak (MoU)', StagePhase::Initial, 1, false],
            ['dokumen_pra_registrasi', 'Dokumen pra-registrasi', StagePhase::PreRegistration, 1, false],
            ['verifikasi_pra_registrasi', 'Verifikasi pra-registrasi', StagePhase::PreRegistration, 1, false],
            ['uji_laboratorium', 'Uji laboratorium eksternal', StagePhase::PreRegistration, 14, false],
            ['menunggu_bpom_pra_registrasi', 'Menunggu hasil BPOM pra-registrasi', StagePhase::PreRegistration, null, true],
            ['dokumen_registrasi', 'Dokumen registrasi', StagePhase::Registration, 30, false],
            ['verifikasi_pengajuan_registrasi', 'Verifikasi dan pengajuan registrasi', StagePhase::Registration, 1, false],
            ['menunggu_bpom_registrasi', 'Menunggu hasil BPOM registrasi', StagePhase::Registration, null, true],
            ['unggah_desain_kemasan', 'Unggah desain kemasan', StagePhase::Registration, null, true],
            ['input_nie', 'Input NIE', StagePhase::Registration, 1, false],
            ['perbaikan_dokumen', 'Perbaikan dokumen', StagePhase::BothPhases, 5, false],
        ];

        foreach ($stages as $order => [$code, $name, $phase, $slaDays, $waitsForExternal]) {
            Stage::query()->firstOrCreate(['code' => $code], [
                'name' => $name,
                'phase' => $phase,
                'sort_order' => $order + 1,
                'sla_days' => $slaDays,
                'waits_for_external' => $waitsForExternal,
            ]);
        }
    }
}
