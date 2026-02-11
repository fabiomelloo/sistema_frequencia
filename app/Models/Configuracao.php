<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        $config = self::where('chave', $chave)->first();
        return $config ? $config->valor : $default;
    }

    /**
     * Define ou atualiza o valor de uma configuração.
     */
    public static function set(string $chave, string $valor, ?string $descricao = null): self
    {
        return self::updateOrCreate(
            ['chave' => $chave],
            array_filter([
                'valor' => $valor,
                'descricao' => $descricao,
            ])
        );
    }

    /**
     * Retorna valor como inteiro.
     */
    public static function getInt(string $chave, int $default = 0): int
    {
        return (int) (self::get($chave) ?? $default);
    }
}
