<?php

namespace App\Http\Controllers;

use App\Actions\SaveMasterRecord;
use App\Http\Requests\SaveHolidayRequest;
use App\Models\Holiday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    public function store(SaveHolidayRequest $request, SaveMasterRecord $save): RedirectResponse
    {
        $save->handle(new Holiday, 'holiday', $request->validated(), $request->user());

        return back()->with('status', 'Hari libur berhasil ditambahkan.');
    }

    public function update(SaveHolidayRequest $request, Holiday $holiday, SaveMasterRecord $save): RedirectResponse
    {
        $save->handle($holiday, 'holiday', $request->validated(), $request->user());

        return back()->with('status', 'Hari libur berhasil diperbarui.');
    }

    public function destroy(Request $request, Holiday $holiday, SaveMasterRecord $save): RedirectResponse
    {
        $save->delete($holiday, 'holiday', $request->user());

        return back()->with('status', 'Hari libur berhasil dihapus.');
    }
}
