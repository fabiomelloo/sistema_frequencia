<?php

namespace App\Traits;

trait MaskedCpf
{
    /**
     * Accessor para CPF mascarado (formato: XXX.XXX.XXX-XX).
     * Retorna CPF mascarado para exibição, mas mantém valor completo no banco.
     * 
     * Uso: $servidor->cpf_masked ou $servidor->getCpfMaskedAttribute()
     */
    public function getCpfMaskedAttribute(): ?string
    {
        $cpf = $this->attributes['cpf'] ?? null;
        
        if (!$cpf) {
            return null;
        }

        // Remove caracteres não numéricos
        $cpfLimpo = preg_replace('/\D/', '', $cpf);

        // Se não tiver 11 dígitos, retorna como está
        if (strlen($cpfLimpo) !== 11) {
            return $cpf;
        }

        // Aplica máscara: XXX.XXX.XXX-XX
        return substr($cpfLimpo, 0, 3) . '.' . 
               substr($cpfLimpo, 3, 3) . '.' . 
               substr($cpfLimpo, 6, 3) . '-' . 
               substr($cpfLimpo, 9, 2);
    }

    /**
     * Accessor para CPF parcialmente mascarado (formato: XXX.***.***-XX).
     * Útil para exibição em listagens onde não se precisa do CPF completo.
     * 
     * Uso: $servidor->cpf_partial
     */
    public function getCpfPartialAttribute(): ?string
    {
        $cpf = $this->attributes['cpf'] ?? null;
        
        if (!$cpf) {
            return null;
        }

        $cpfLimpo = preg_replace('/\D/', '', $cpf);

        if (strlen($cpfLimpo) !== 11) {
            return '***.***.***-**';
        }

        // Mostra apenas primeiros 3 dígitos e últimos 2
        return substr($cpfLimpo, 0, 3) . '.***.***-' . substr($cpfLimpo, 9, 2);
    }

    /**
     * Accessor para CPF completamente mascarado (formato: ***.***.***-**).
     * Útil para usuários sem permissão de visualizar CPF completo.
     * 
     * Uso: $servidor->cpf_hidden
     */
    public function getCpfHiddenAttribute(): string
    {
        return '***.***.***-**';
    }

    /**
     * Verifica se o usuário atual tem permissão para ver CPF completo.
     * Por padrão, apenas usuários CENTRAL podem ver CPF completo.
     * 
     * Uso: $servidor->podeVerCpfCompleto()
     */
    public function podeVerCpfCompleto(): bool
    {
        if (!auth()->check()) {
            return false;
        }

        // Apenas usuários CENTRAL podem ver CPF completo
        return auth()->user()->isCentral();
    }

    /**
     * Retorna CPF formatado de acordo com permissão do usuário.
     * 
     * Uso: $servidor->cpf_formatado
     */
    public function getCpfFormatadoAttribute(): string
    {
        if ($this->podeVerCpfCompleto()) {
            return $this->cpf_masked ?? 'N/A';
        }

        // Usuários SETORIAL veem apenas parcial
        return $this->cpf_partial ?? '***.***.***-**';
    }
}
