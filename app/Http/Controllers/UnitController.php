<?php

namespace App\Http\Controllers;

use App\Http\Requests\Product\StoreUnitRequest;
use App\Http\Requests\Product\UpdateUnitRequest;
use App\Models\Unit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UnitController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:manage-units');
    }

    public function index(Request $request)
    {
        $units = Unit::query()
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('short_name', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate(20);

        return Inertia::render('Units/Index', [
            'units' => $units,
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(StoreUnitRequest $request)
    {
        try {
            Unit::create($request->validated());

            return redirect()->route('units.index')
                ->with('success', 'Satuan berhasil ditambahkan');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Gagal menambahkan satuan: ' . $e->getMessage());
        }
    }

    public function update(UpdateUnitRequest $request, Unit $unit)
    {
        try {
            $unit->update($request->validated());

            return redirect()->route('units.index')
                ->with('success', 'Satuan berhasil diperbarui');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Gagal memperbarui satuan: ' . $e->getMessage());
        }
    }

    public function destroy(Unit $unit)
    {
        try {
            // Check if unit has products
            if ($unit->products()->exists()) {
                return redirect()->back()
                    ->with('error', 'Satuan tidak dapat dihapus karena memiliki produk terkait');
            }

            $unit->delete();

            return redirect()->route('units.index')
                ->with('success', 'Satuan berhasil dihapus');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Gagal menghapus satuan: ' . $e->getMessage());
        }
    }
}
