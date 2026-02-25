<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Configuracao extends Model
{
    protected $table = 'configuracoes';

    protected $fillable = [
        'chave',
        'valor',
        'descricao',
    ];

    /**
     * Obtém o valor de uma configuração por chave.
     */
    public static function get(string $chave, $default = null): ?string
    {
        return Cache::remember('configuracao_'.$chave, now()->addDays(7), function () use ($chave, $default) {
            $config = self::where('chave', $chave)->first();
            return $config ? $config->valor : $default;
        });
    }

    /**
     * Define ou atualiza o valor de uma configuração.
     */
    public static function set(string $chave, string $valor, ?string $descricao = null): self
    {
        Cache::forget('configuracao_'.$chave);
        
        return self::updateOrCreate(
            ['chave' => $chave],
            array_filter([
                'valor' => $valor,
                'descricao' => $descricao,
            ])
        );
    }

    protected static function booted()
    {
        static::saved(function ($configuracao) {
            Cache::forget('configuracao_' . $configuracao->chave);
        });

        static::deleted(function ($configuracao) {
            Cache::forget('configuracao_' . $configuracao->chave);
        });
    }

    /**
     * Retorna valor como inteiro.
     */
    public static function getInt(string $chave, int $default = 0): int
    {
        return (int) (self::get($chave) ?? $default);
    }
}
