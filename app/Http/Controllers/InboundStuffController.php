<?php

namespace App\Http\Controllers;

use App\Models\Stuff;
use App\Models\StuffStock;
use Illuminate\Http\Request;
use App\Helpers\ApiFormatter;
use App\Models\InboundStuff; 
use Illuminate\Support\Facades\Validator;

class InboundStuffController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }
    public function index()
    {
        $inboundStock = InboundStuff::with('stuff', 'stuff.stock')->get();


        return ApiFormatter::sendResponse(200, true, 'Lihat semua stok barang', $inboundStock);
    }


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'stuff_id'   => 'required',
            'total' => 'required',
            'date' => 'required',
            'proff_file' => 'required|file',
        ]);


        if ($validator->fails()) {
            return ApiFormatter::sendResponse(400, false, 'Semua Kolom Wajib Diisi!', $validator->errors());
        } else {
            // mengambil file
            $file = $request->file('proff_file');
            $fileName = $request->input('stuff_id') . '_' . strtotime($request->input('date')) . strtotime(date('H:i')) . '.' . $file->getClientOriginalExtension();
            $file->move('proff', $fileName);
            $inbound = InboundStuff::create([
                'stuff_id'     => $request->input('stuff_id'),
                'total'   => $request->input('total'),
                'date'   => $request->input('date'),
                'proff_file'   => $fileName,
            ]);

            $stock = StuffStock::where('stuff_id', $request->input('stuff_id'))->first();
            
            $total_stock = (int)$stock->total_available + (int)$request->input('total');

            $stock->update([
                'total_available' => (int)$total_stock
            ]);


            if ($inbound && $stock) {
                return ApiFormatter::sendResponse(201, true, 'Barang Masuk Berhasil Disimpan!');
            } else {
                return ApiFormatter::sendResponse(400, false, 'Barang Masuk Gagal Disimpan!');
            }


        }
    }

    public function show($id)
    {
        try {
            $inbound = InboundStuff::with('stuff', 'stuff.stock')->findOrFail($id);


            return ApiFormatter::sendResponse(200, true, "Lihat Barang Masuk dengan id $id", $inbound);
        } catch (\Throwable $th) {
            return ApiFormatter::sendResponse(404, false, "Data dengan id $id tidak ditemukan", $th->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $inbound = InboundStuff::with('stuff', 'stuff.stock')->findOrFail($id);


            $stuff_id = ($request->stuff_id) ? $request->stuff_id : $inbound->stuff_id;
            $total = ($request->total) ? $request->total : $inbound->total;
            $date = ($request->date) ? $request->date : $inbound->date;


            if ($request->file('proff_file') !== NULL) {
                $file = $request->file('proff_file');
                $fileName = $stuff_id . '_' . strtotime($date) . strtotime(date('H:i')) . '.' . $file->getClientOriginalExtension();
                $file->move('proff', $fileName);
            } else {
                $fileName = $inbound->proff_file;
            }
            $total_s = $total - $inbound->total;
            $total_stock = (int)$inbound->stuff->stock->total_available + $total_s;
            $inbound->stuff->stock->update([
                'total_available' => (int)$total_stock
            ]);
            if ($inbound) {
                $inbound->update([
                    'stuff_id' => $stuff_id,
                    'total' => $total,
                    'date' => $date,
                    'proff_file' => $fileName
                ]);
                return ApiFormatter::sendResponse(200, true, "Berhasil Ubah Data Barang Masuk dengan id $id", $inbound);
            } else {
                return ApiFormatter::sendResponse(400, false, "Proses gagal!");
            }
        } catch (\Throwable $th) {
            return ApiFormatter::sendResponse(400, false, "Proses Gagal!", $th->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $inbound = InboundStuff::findOrFail($id);
            $stock = StuffStock::where('stuff_id', $inbound->stuff_id)->first();

            // Memeriksa apakah total_available lebih kecil dari total pada inbound
            if ($stock->total_available < $inbound->total) {
                return ApiFormatter::sendResponse(400, false, 'bad reuest', 'Jumlah total inbound yang akan dihapus lebih besar dari total available stuff saat ini.');
            }

            // Menghitung total_available dan total_defect yang baru setelah data dihapus
            $available = $stock->total_available - $inbound->total;
            $defect = $stock->total_defect;

            // Memperbarui total_available dan total_defect pada stuff_stock
            $stock->update([
                'total_available' => $available,
                'total_defect' => $defect
            ]);

            // Menghapus data inbound stuff
            $inbound->delete();

            return ApiFormatter::sendResponse(200, true, "Berhasil Hapus Data dengan id $id", ['id' => $id]);
        } catch (\Throwable $th) {
            return ApiFormatter::sendResponse(400, false, "Proses gagal!", $th->getMessage());
        }
    }

    public function deleted()
    {
        try {
            $inbounds = InboundStuff::onlyTrashed()->get();

            return ApiFormatter::sendResponse(200, true, "Lihat Data Barang Masuk yang dihapus", $inbounds);
        } catch (\Throwable $th) {
            //throw $th;
            return ApiFormatter::sendResponse(404, false, "Proses gagal! Silakan coba lagi!", $th->getMessage());
        }
    }

    public function restore($id)
{
    try {
        $inbound = InboundStuff::onlyTrashed()->with('stuff.stock')->findOrFail($id);
        $inbound->restore(); // First restore the item

        $stock = StuffStock::where('stuff_id', $inbound->stuff_id)->firstOrFail();

        // Calculate the new total_available by adding the restored inbound total to the current total_available
        $new_total_available = $stock->total_available + $inbound->total;

        // Update the stock with the new total
        $stock->update(['total_available' => $new_total_available]);

        return ApiFormatter::sendResponse(200, true, "Berhasil Mengembalikan data yang telah dihapus!", $inbound);
    } catch (\Throwable $th) {
        return ApiFormatter::sendResponse(404, false, "Proses gagal! Silakan coba lagi!", $th->getMessage());
    }
}

    public function restoreAll()
    {
        try {
            $inbounds = InboundStuff::onlyTrashed();

            foreach ($inbounds->get() as $inbound) {
                $stock = StuffStock::where('stuff_id', $inbound->stuff_id)->first();

                $available = $stock->total_available + $inbound->total;
                $available_min = $inbound->total - $stock->total_available;
                $defect = ($available_min < 0) ? $stock->total_defect + ($available_min * -1) : $stock->total_defect;

                $stock->update([
                    'total_available' => $available,
                    'total_defect' => $defect
                ]);
            }

            $inbounds->restore();

            return ApiFormatter::sendResponse(200, true, "Berhasil mengembalikan semua data yang telah di hapus!");
        } catch (\Throwable $th) {
            //throw $th;
            return ApiFormatter::sendResponse(404, false, "Proses gagal! Silakan coba lagi!", $th->getMessage());
        }
    }

    public function permanentDelete($id)
    {
        try {
            $inbound = InboundStuff::onlyTrashed()->where('id', $id);

            $stock = StuffStock::where('stuff_id', $inbound->stuff_id)->first();

            $available = $stock->total_available - $inbound->total;
            $defect = ($available < 0) ? $stock->total_defect + ($available * -1) : $stock->total_defect;

            $stock->update([
                'total_available' => $available,
                'total_defect' => $defect
            ]);

            $inbound->forceDelete();

            return ApiFormatter::sendResponse(200, true, "Berhasil hapus permanen data yang telah di hapus!", ['id' => $id]);
        } catch (\Throwable $th) {
            //throw $th;
            return ApiFormatter::sendResponse(404, false, "Proses gagal! Silakan coba lagi!", $th->getMessage());
        }
    }
    
    public function permanentDeleteAll()
    {
        try {
            $inbounds = InboundStuff::onlyTrashed();

            foreach ($inbounds->get() as $inbound) {
                $stock = StuffStock::where('stuff_id', $inbound->stuff_id)->first();

                $available = $stock->total_available - $inbound->total;
                $defect = ($available < 0) ? $stock->total_defect + ($available * -1) : $stock->total_defect;

                $stock->update([
                    'total_available' => $available,
                    'total_defect' => $defect
                ]);

                $inbound->forceDelete();
            }

            $inbounds->forceDelete();

            return ApiFormatter::sendResponse(200, true, "Berhasil hapus permanen semua data yang telah di hapus!");
        } catch (\Throwable $th) {
            //throw $th;
            return ApiFormatter::sendResponse(404, false, "Proses gagal! Silakan coba lagi!", $th->getMessage());
        }
    }

}
