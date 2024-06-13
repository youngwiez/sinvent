<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\BarangKeluar;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class BarangKeluarController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // eloquent
        if ($request->search) {
            $barangkeluar = BarangKeluar::select('barangkeluar.*', 'barang.seri as seri')
                            ->join('barang', 'barangkeluar.barang_id', '=', 'barang.id')
                            ->where('barangkeluar.id','like','%'.$request->search.'%')
                            ->orWhere('barangkeluar.tgl_keluar','like','%'.$request->search.'%')
                            ->orWhere('barangkeluar.qty_keluar','like','%'.$request->search.'%')
                            ->orWhereHas('barang', function($query) use ($request) {
                                $query->where('seri','like','%'.$request->search.'%')
                                    ->orWhere('merk','like','%'.$request->search.'%');
                            })
                            ->paginate(3);
        } else {
            $barangkeluar = BarangKeluar::select('barangkeluar.*', 'barang.seri as seri')
                                    ->join('barang', 'barangkeluar.barang_id', '=', 'barang.id')
                                    ->paginate(3);
        }
        return view('dashboard.barangkeluar.index', ['barangkeluar' => $barangkeluar]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // query builder
        $barang = DB::table('barang')->get();
        return view('dashboard.barangkeluar.create', ['barang' => $barang]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // eloquent
        $validator = Validator::make($request->all(), [
            'qty_keluar' => 'required|integer',
            'barang_id' => 'required|exists:barang,id',
        ]);
        if ($validator->fails()) {
            return redirect()->route('barangkeluar.create')
                ->withErrors($validator)
                ->withInput();
        }
        $barang = DB::table('barang')
                ->where('id', $request->barang_id)
                ->first();
        if ($barang->stok < $request->qty_keluar) {
            return redirect()->route('barangkeluar.create')
                ->withErrors(['qty_keluar' => 'Stok yang dimasukkan melebihi jumlah barang yang tersedia'])
                ->withInput();
        }

        // transaction
        try {
            DB::beginTransaction();
            DB::table('barangkeluar')->insert([
                'tgl_keluar'     => now()->toDateString(),
                'qty_keluar'     => $request->qty_keluar,
                'barang_id'     => $request->barang_id,
            ]);
            DB::commit();
        } catch (\Exception $e) {
            report($e);
            DB::rollBack();
            return redirect()->route('barangkeluar.create')->with(['error' => 'Terjadi kesalahan saat menyimpan data!']);
        }
        return redirect()->route('barangkeluar.index')->with(['success' => 'Data barang keluar berhasil disimpan!']);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // eloquent
        $rowkeluar = BarangKeluar::select('barangkeluar.*', 'barang.seri as seri')
                                ->join('barang', 'barangkeluar.barang_id', '=', 'barang.id')
                                ->findOrfail($id);
        return view('dashboard.barangkeluar.show', compact('rowkeluar'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $idkeluar = BarangKeluar::find($id);
        $barang_id = Barang::all();
        return view('dashboard.barangkeluar.edit', compact('idkeluar','barang_id'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'qty_keluar'  => 'required',
            'barang_id'   => 'required'
        ]);
        $barang_id = BarangKeluar::find($id);
        $barang_id->update([
            'tgl_keluar'  => now()->toDateString(),
            'qty_keluar'  => $request->qty_keluar,
            'barang_id'  => $request->barang_id
        ]);
        return redirect()->route('barangkeluar.index')->with(['success' => 'Data barang keluar berhasil diubah!']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $barangkeluar = BarangKeluar::find($id);
        $barangkeluar->delete();
        return redirect()->route('barangkeluar.index')->with(['success' => 'Data barang keluar berhasil dihapus!']);
    }
}