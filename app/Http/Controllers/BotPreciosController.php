<?php

namespace App\Http\Controllers;

use App\Models\CostoEvento;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BotPreciosController extends Controller
{
    public function index()
    {
        return Inertia::render('bot-precios', [
            'precios' => CostoEvento::orderBy('id')->get(),
        ]);
    }

    public function update(Request $request, CostoEvento $costoEvento)
    {
        $request->validate([
            'precio' => ['required', 'numeric', 'min:0', 'max:9999999'],
        ]);

        $costoEvento->update(['precio' => $request->precio]);

        return back()->with('success', 'Precio actualizado.');
    }

    public function template()
    {
        $costos = CostoEvento::orderBy('id')->get(['concepto', 'descripcion', 'precio']);

        $rows   = [];
        $rows[] = ['concepto', 'descripcion', 'precio'];
        foreach ($costos as $c) {
            $rows[] = [$c->concepto, $c->descripcion, $c->precio];
        }

        $output = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="precios_template.csv"',
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:csv,txt', 'max:512'],
        ]);

        $path    = $request->file('archivo')->getRealPath();
        $handle  = fopen($path, 'r');
        $header  = fgetcsv($handle); // saltar encabezado

        if (!$header || !in_array('concepto', $header) || !in_array('precio', $header)) {
            fclose($handle);
            return back()->withErrors(['archivo' => 'El archivo no tiene las columnas "concepto" y "precio".']);
        }

        $conceptoIdx = array_search('concepto', $header);
        $precioIdx   = array_search('precio',   $header);

        $actualizados = 0;
        $errores      = [];

        while (($row = fgetcsv($handle)) !== false) {
            $concepto = trim($row[$conceptoIdx] ?? '');
            $precio   = trim($row[$precioIdx]   ?? '');

            if ($concepto === '') continue;

            if (!is_numeric($precio) || (float)$precio < 0) {
                $errores[] = "Precio inválido para «{$concepto}»: {$precio}";
                continue;
            }

            $updated = CostoEvento::where('concepto', $concepto)
                ->update(['precio' => (float)$precio]);

            if ($updated) {
                $actualizados++;
            } else {
                $errores[] = "Concepto no encontrado: «{$concepto}»";
            }
        }

        fclose($handle);

        $msg = "Se actualizaron {$actualizados} precio(s).";
        if (!empty($errores)) {
            $msg .= ' Errores: ' . implode(' | ', $errores);
        }

        return back()->with('success', $msg);
    }
}
