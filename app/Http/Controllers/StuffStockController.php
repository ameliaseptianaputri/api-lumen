<?php

namespace App\Http\Controllers;

use App\Models\Stuff;
use App\Models\StuffStock;
use Illuminate\Http\Request;
use App\Helpers\ApiFormatter;
use Illuminate\Support\Facades\Validator;

class StuffStockController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }
    public function index()
    {
        $stuffStock = StuffStock::with('stuff')->get();
        $stuff = Stuff::get();
        $stock = StuffStock::get();

        $data = ['barang' => $stuff, 'stock' => $stock];

        return ApiFormatter::sendResponse(200, true, 'lihat semua barang', $stuffStock);

    }

    public function store(Request $request)
    {
    //     $validator = Validator::make($request->all(), [
    //         'stuff_id' => 'required',
    //         'total_available' => 'required',
    //         'total_defec' => 'required',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'semua kolom wajib diisi',
    //             'data' => $validator->errors()
    //         ], 400);
    //     } else {
    //         $stock = StuffStock::updateOrCreate([
    //             'stuff_id' => $request->input('stuff_id')
    //         ],[
    //             'total_available' => $request->input('total_available'), 
    //             'total_defec' => $request->input('total_defec'),
    //         ]);

    //         if ($stock) {
    //             return response()->json([
    //                 'success' => true,
    //                 'message' => 'stock barang berhasil disimpan',
    //                 'data' => $stock
    //             ], 201);
    //         } else {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'stock barang gagal disimpan',
    //             ], 400);
    //         }
    //     }
    // }
    try{
        //validasi
        $this->validate($request, [
            'stuff_id' => 'required',
            'total_available' => 'required',
            'total_defec' => 'required',
        ]);
        $stock = StuffStock::updateOrCreate([
            'stuff_id' => $request->input('stuff_id')
        ],[
            'total_available' => $request->input('total_available'), 
            'total_defec' => $request->input('total_defec'),
        ]);
        return ApiFormatter::sendResponse(201, true, 'barang berhasil disimpan!', $stock);
    } catch (\Throwable $th) {
        //throw $th
        if ($th->validator->error()) {
            return ApiFormatter::sendResponse(400, false, 'Terdapat kesalahan input Silahkan coba lagi', $th->validator->error());
        } else {
            return ApiFormatter::sendResponse(400, false, 'Terdapat kesalahan input Silahkan coba lagi', $th->getMessage());
        }
    }
}

    public function show($id)
    {
        // try {
        //     $stock = StuffStock::with('stuff')->find($id);

        //     return response()->json([
        //         'success' => true,
        //         'message' => "lihat stock barang dengan id $id",
        //         'data' => $stock
        //     ], 200);
        // } catch (\Throwable $th) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => "data dengan id $id tidak ditemukan"
        //     ], 404);
        // }
        try {
            $stock = Stuffstock::with('stuff')->findOrFail($id);

            return ApiFormatter::sendResponse(200, true, "Lihat barang dengan id $id", $stock);
        } catch (\Throwable $th) {
            return ApiFormatter::sendResponse(404, false, "Data dengan id $id tidak ditemukan");
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $stock = StuffStock::with('stuff')->findOrFail($id);
            $total_available = ($request->total_available) ? $request->total_available : $stock->total_available;
            $total_defec = ($request->total_defec) ? $request->total_defec : $stock->total_defec;

        //     if ($stock) {
        //         $stock->update([
        //             'total_available' => $total_available,
        //             'total_defec' => $total_defec,
        //         ]);

        //         return response()->json([
        //             'success' => true,
        //             'message' => "berhasil ubah data stock dengan id $id",
        //             'data' => $stock
        //         ], 200);
        //     } else {
        //         return response()->json([
        //             'success' => false,
        //             'message' => "proses gagal"
        //         ], 404);
        //     }
        // } catch (\Throwable $th) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => " proses gagal data dengan id $id tidak ditemukan"
        //     ], 404);
            $stock->update([
                'total_available' => $total_available,
                'total_defec' => $total_defec
            ]);

            return ApiFormatter::sendResponse(400, true, "Berhasil ubah data dengan id $id");
        } catch (\Throwable $th) {
            return ApiFormatter::sendResponse(404, false, "Proses gagal, Silahkan coba lagi", $th->getMessage());
        }
    }


    public function destroy($id)
    {
        try {
            $stock = StuffStock::findOrFail($id);

            $stock->delete();

        //     return response()->json([
        //         'success' => true,
        //         'message' => "berhasil hapus data dengan id $id",
        //         'data' => [ 'id' => $id,
        //         ]
        //     ], 200);
        // } catch (\Throwable $th) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => "proses gagal data dengan id $id tidak ditemukan"
        //     ], 404);
        // }

            return ApiFormatter::sendResponse(200, true, "Berhasil hapus data dengan id $id", ['id' => $id]);
        } catch (\Throwable $th) {
            return ApiFormatter::sendResponse(404, false, "Proses gagal silahkan coba lagi", $th->getMessage());
        };
    }

    public function deleted()
    {
        try{
            $stocks = StuffStock::onlyTrashed()->get();

            return ApiFormatter::sendResponse(200, true, "Lihat data barang yang dihapus", $stocks);
        } catch (\Throwable $th) {
            return ApiFormatter::sendResponse(404, false, 'Proses gagal! silahkan coba lagi', $th->getMessage());
        }  
    }

    public function restore($id)
    {
        try{
            $stock = Stuffstock::onlyTrashed()->findOrFail($id);
            $has_stock = StuffStock::where('stuff_id', $stock->stuff_id)->get();

            if($has_stock->count() == 1) { //ngecek kalo stoknya udah ada gabisa di restore lagi
                $message = "Data stock sudah ada, tidak boleh ada duplikat data stock untuk satu barang, silahkan update data dengan id stock $stock->stuff_id";
            }else{
                $stock -> restore();
                $message = "Berhasil mengembalikan data yang telat dihappus!";
            }
            return ApiFormatter::sendResponse(200, true, $message, ['id' => $id, 'stuff_id' => $stock->stuff_id]);
        } catch (\Throwable $th) {
            return ApiFormatter::sendResponse(404, false, 'Proses gagal silahkan coba lagi', $th->getMessage());
        }     
    }

    public function restoreAll()
    {
        try{
            $stocks = StuffStock::onlyTrashed();
            $stocks->restore();

            return ApiFormatter::sendResponse(200, true, "Berhasil mengembalikan data yang telah dihapus!");
        } catch (\Throwable $th) {
            return ApiFormatter::sendResponse(404, false, 'Proses gagal! silahkan coba lagi', $th->getMessage());
        }
    }

    public function permanentDelete($id)
    {
        try{
            $stocks = StuffStock::onlyTrashed()->where('id', $id)->forceDelete();

            return ApiFormatter::sendResponse(200, true, "Berhasil hapus permanen data yang telah dihapus!", ['id' => $id]);
        } catch (\Throwable $th) {
            return ApiFormatter::sendResponse(404, false, 'Proses gagal silahkan coba lagi', $th->getMessage());
        }
    }

    public function permanentDeleteAll()
    {
        try{
            $stocks = StuffStock::onlyTrashed();
            
            $stocks -> forceDelete();

            return ApiFormatter::sendResponse(200, true, "Berhasil hapus permanen semua data yang telah dihapus!");
        } catch (\Throwable $th) {
            return ApiFormatter::sendResponse(404, false, 'Proses gagal! silahkan coba lagi', $th->getMessage());
        }
    }
}