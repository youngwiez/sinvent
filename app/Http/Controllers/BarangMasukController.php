<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\BarangMasuk;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class BarangMasukController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // eloquent
        if ($request->search) {
            $barangmasuk = BarangMasuk::select('barangmasuk.*', 'barang.seri as seri')
                            ->join('barang', 'barangmasuk.barang_id', '=', 'barang.id')
                            ->where('barangmasuk.id','like','%'.$request->search.'%')
                            ->orWhere('barangmasuk.tgl_masuk','like','%'.$request->search.'%')
                            ->orWhere('barangmasuk.qty_masuk','like','%'.$request->search.'%')
                            ->orWhereHas('barang', function($query) use ($request) {
                                $query->where('seri','like','%'.$request->search.'%')
                                    ->orWhere('merk','like','%'.$request->search.'%');
                            })
                            ->paginate(3);
        } else {
            $barangmasuk = BarangMasuk::select('barangmasuk.*', 'barang.seri as seri')
                                    ->join('barang', 'barangmasuk.barang_id', '=', 'barang.id')
                                    ->paginate(3);
        }
        return view('dashboard.barangmasuk.index', ['barangmasuk' => $barangmasuk]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // query builder
        $barang = DB::table('barang')->get();
        return view('dashboard.barangmasuk.create', ['barang' => $barang]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // eloquent
        $validator = Validator::make($request->all(), [
            'qty_masuk' => 'required|integer',
            'barang_id' => 'required|exists:barang,id',
        ]);
        if ($validator->fails()) {
            return redirect()->route('barangmasuk.create')
                ->withErrors($validator)
                ->withInput();
        }

        // transaction
        try {
            DB::beginTransaction();
            DB::table('barangmasuk')->insert([
                'tgl_masuk'     => now()->toDateString(),
                'qty_masuk'     => $request->qty_masuk,
                'barang_id'     => $request->barang_id,
            ]);
            DB::commit();
        } catch (\Exception $e) {
            report($e);
            DB::rollBack();
            return redirect()->route('barangmasuk.create')->with(['error' => 'Terjadi kesalahan saat menyimpan data!']);
        }
        return redirect()->route('barangmasuk.index')->with(['success' => 'Data barang masuk berhasil disimpan!']);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // eloquent
        $rowmasuk = BarangMasuk::select('barangmasuk.*', 'barang.seri as seri')
                                ->join('barang', 'barangmasuk.barang_id', '=', 'barang.id')
                                ->findOrfail($id);
        return view('dashboard.barangmasuk.show', compact('rowmasuk'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $idmasuk = BarangMasuk::find($id);
        $barang_id = Barang::all();
        return view('dashboard.barangmasuk.edit', compact('idmasuk','barang_id'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'qty_masuk'  => 'required',
            'barang_id'   => 'required'
        ]);
        $barang_id = BarangMasuk::find($id);
        $barang_id->update([
            'tgl_masuk'  => now()->toDateString(),
            'qty_masuk'  => $request->qty_masuk,
            'barang_id'  => $request->barang_id
        ]);
        return redirect()->route('barangmasuk.index')->with(['success' => 'Data barang masuk berhasil diubah!']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $barangmasuk = BarangMasuk::find($id);
        $barangmasuk->delete();
        return redirect()->route('barangmasuk.index')->with(['success' => 'Data barang masuk berhasil dihapus!']);
    }
}
