<?php

namespace App\Http\Controllers;

use App\Actions\EmitirNotaFiscal;
use App\Models\ConfiguracaoFiscal;
use App\Models\DocumentoFiscal;
use App\Models\Venda;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class FiscalController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');

        $documentos = DocumentoFiscal::with('venda')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (DocumentoFiscal $d) => [
                'id' => $d->id,
                'venda_id' => $d->venda_id,
                'total' => (float) ($d->venda?->total ?? 0),
                'modelo' => $d->modelo,
                'ambiente' => (int) $d->ambiente,
                'status' => $d->status,
                'numero' => $d->numero,
                'serie' => $d->serie,
                'chave' => $d->chave,
                'mensagem' => $d->mensagem,
                'tentativas' => (int) $d->tentativas,
                'data' => Carbon::parse($d->autorizada_em ?? $d->created_at)->format('d/m/Y H:i'),
                'tem_xml' => filled($d->xml_path),
                'tem_pdf' => filled($d->pdf_path),
                'pode_enviar' => $d->podeEnviar(),
            ]);

        $config = ConfiguracaoFiscal::atual();

        return Inertia::render('Fiscal/Index', [
            'documentos' => $documentos,
            'contagem' => DocumentoFiscal::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'filtro' => $status,
            'config' => ['pronta' => $config->prontaParaEmitir(), 'ambiente' => (int) $config->ambiente],
        ]);
    }

    public function emitir(Venda $venda, EmitirNotaFiscal $emitir)
    {
        try {
            $doc = $emitir->executar($venda);
        } catch (\RuntimeException|\LogicException $e) {
            // Bloqueios da action: venda cancelada, sem configuração, produto sem NCM...
            return back()->with('error', $e->getMessage());
        }

        return $doc->status === 'autorizada'
            ? back()->with('success', "Nota nº {$doc->numero} autorizada.")
            : back()->with('error', 'Nota não autorizada: '.($doc->mensagem ?: $doc->status));
    }

    public function arquivo(DocumentoFiscal $documento, string $tipo)
    {
        $caminho = $tipo === 'xml' ? $documento->xml_path : $documento->pdf_path;

        abort_unless($caminho && Storage::exists($caminho), 404);

        return Storage::download($caminho);
    }
}