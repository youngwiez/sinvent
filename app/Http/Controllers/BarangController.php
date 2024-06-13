<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\Kategori;
use App\Models\BarangMasuk;
use App\Models\BarangKeluar;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class BarangController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // eloquent
        if ($request->search) {
            $barang = Barang::with('kategori')
                            ->where('id','like','%'.$request->search.'%')
                            ->orWhere('merk', 'like','%'.$request->search.'%')
                            ->orWhere('seri','like','%'.$request->search.'%')
                            ->orWhere('spesifikasi','like','%'.$request->search.'%')
                            ->orWhere('stok','like','%'.$request->search.'%')
                            ->orWhereHas('kategori', function($query) use ($request) {
                                $query->where('deskripsi','like','%'.$request->search.'%');
                            })
                            ->paginate(3);
        } else {
            $barang = Barang::with('kategori')->paginate(3);
        }
        return view('dashboard.barang.index', ['barang' => $barang]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // eloquent
        $kat_id = Kategori::all();
        return view('dashboard.barang.create',compact('kat_id'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'merk'          => 'required|string|max:50',
            'seri'          => 'nullable|string|max:50|unique:barang,seri',
            'spesifikasi'   => 'nullable|string',
            'kategori_id'   => 'required|exists:kategori,id',
        ], [
            'seri.unique'   => 'Barang dengan seri ini sudah ada, silakan masukkan barang yang berbeda.',
        ]);
        if ($validator->fails()) {
            return redirect()->route('barang.create')
                ->withErrors($validator)
                ->withInput();
        }
        
        // transaction
        try {
            DB::beginTransaction();
            DB::table('barang')->insert([
                'merk'          => $request->merk,
                'seri'          => $request->seri,
                'spesifikasi'   => $request->spesifikasi,
                'stok'          => 0,
                'kategori_id'   => $request->kategori_id,
            ]);
            DB::commit();
        } catch (\Exception $e) {
            report($e);
            DB::rollBack();
            return redirect()->route('barang.create')->with(['error' => 'Terjadi kesalahan saat menyimpan data!']);
        }
        return redirect()->route('barang.index')->with(['success' => 'Data barang berhasil disimpan!']);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $rowbarang = Barang::with('kategori')->findOrFail($id);
        return view('dashboard.barang.show', compact('rowbarang'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        // eloquent
        $idbar = Barang::find($id);
        $kategori_id = Kategori::all();
        return view('dashboard.barang.edit', compact('idbar','kategori_id'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'merk'          => 'required|string|max:50',
            'seri'          => 'nullable|string|max:50|unique:barang,seri,'.$id,
            'spesifikasi'   => 'nullable|string',
            'kategori_id'   => 'required|exists:kategori,id',
        ], [
            'seri.unique'   => 'Barang dengan seri ini sudah ada, silakan masukkan barang yang berbeda.',
        ]);
        if ($validator->fails()) {
            return redirect()->route('barang.edit', ['barang' => $id])
                ->withErrors($validator)
                ->withInput();
        }
        $idbar = Barang::find($id);
        $idbar->update([
            'merk'          => $request->merk,
            'seri'          => $request->seri,
            'spesifikasi'   => $request->spesifikasi,
            'kategori_id'   => $request->kategori_id,
        ]);
        return redirect()->route('barang.index')->with(['success' => 'Data barang berhasil diubah!']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $barang = Barang::find($id);
        if ($barang->stok > 0) {
            return redirect()->route('barang.index')->with(['error' => 'Data barang gagal dihapus! Barang dengan stok lebih dari 0 tidak dapat dihapus!']);
        }
        $relatedBarangKeluar = BarangKeluar::where('barang_id', $id)->exists();
        if ($relatedBarangKeluar) {
            return redirect()->route('barang.index')->with(['error' => 'Data barang gagal dihapus! Barang masih digunakan dalam tabel Barang Keluar!']);
        }
        $relatedBarangMasuk = BarangMasuk::where('barang_id', $id)->exists();
        if ($relatedBarangMasuk) {
            return redirect()->route('barang.index')->with(['error' => 'Data barang gagal dihapus! Barang masih digunakan dalam tabel Barang Masuk!']);
        }
        $barang->delete();
        return redirect()->route('barang.index')->with(['success' => 'Data barang berhasil dihapus!']);
    }
}
