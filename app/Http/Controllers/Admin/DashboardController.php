<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\TransactionDetail;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {

        $search = $request->input('search');

        $categories = Category::count();
        $suppliers = Supplier::count();
        $products = Product::count();
        $customers = User::count();

        $productInQuantity = TransactionDetail::whereHas('transaction', function ($query) {
            $query->where('type', 'in');
        })->sum('quantity');

        $productOutQuantity = TransactionDetail::whereHas('transaction', function ($query) {
            $query->where('type', 'out');
        })->sum('quantity');

        $productInThisMonth = TransactionDetail::whereHas('transaction', function ($query) {
            $query->where('type', 'in');
        })
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('quantity');
        
        $productOutThisMonth = TransactionDetail::whereHas('transaction', function ($query) {
            $query->where('type', 'out');
        })
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('quantity');
        
        // Ambil parameter per_page dari request, default 10
        $perPage = $request->query('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50]) ? $perPage : 10;

        // --- QUERY UNTUK TABEL STOK RENDAH  ---
        
        // 1. Mulai query dasar
        $lowStockQuery = Product::with('category')->where('quantity', '<=', 10);

        // 2. Terapkan filter pencarian secara kondisional
        if ($search) {
            $lowStockQuery->where(function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                      ->orWhere('code', 'like', '%' . $search . '%');
            });
        }
        
        // 3. Lakukan paginasi dan tambahkan semua parameter request ke link paginasi
        $productsOutStock = $lowStockQuery->latest()->paginate($perPage)->appends($request->query());
        

        $bestProduct = TransactionDetail::with('product')
            ->whereHas('transaction', function($query) {
                $query->where('type', 'out');
            })
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->selectRaw('product_id, SUM(quantity) as total')
            ->groupBy('product_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        if ($bestProduct->isNotEmpty()) {
            $label = $bestProduct->map(fn($item) => $item->product?->name ?? 'Tidak Diketahui')->toArray();
            $total = $bestProduct->map(fn($item) => (int)$item->total)->toArray();
        } else {
            $label = ['Tidak Ada Data'];
            $total = [1];
        }

        $connection = config('database.default');
        $databasePath = config("database.connections.{$connection}.database");
        $databaseDirectory = is_string($databasePath) ? dirname($databasePath) : database_path();
        $diskTotal = @disk_total_space($databaseDirectory) ?: 0;
        $diskFree = @disk_free_space($databaseDirectory) ?: 0;

        $resourceStats = [
            'process_memory' => $this->formatBytes(memory_get_usage(true)),
            'php_memory_limit' => ini_get('memory_limit') ?: 'Tidak dibatasi',
            'database_size' => is_string($databasePath) && is_file($databasePath)
                ? $this->formatBytes(filesize($databasePath))
                : 'Database tidak ditemukan',
            'disk_free' => $this->formatBytes($diskFree),
            'disk_total' => $this->formatBytes($diskTotal),
            'disk_percent' => $diskTotal > 0 ? round((($diskTotal - $diskFree) / $diskTotal) * 100, 1) : 0,
            'checked_at' => now()->format('d-m-Y H:i:s'),
        ];

        return view('admin.dashboard', compact(
            'categories',
            'suppliers',
            'products',
            'customers',
            'productInQuantity',
            'productOutQuantity',
            'productInThisMonth',
            'productOutThisMonth',
            'productsOutStock',
            'label',
            'total',
            'search',
            'resourceStats'
        ));
    }

    private function formatBytes($bytes): string
    {
        $bytes = max(0, (float) $bytes);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? min((int) floor(log($bytes, 1024)), count($units) - 1) : 0;

        return number_format($bytes / (1024 ** $power), $power === 0 ? 0 : 2) . ' ' . $units[$power];
    }
}
