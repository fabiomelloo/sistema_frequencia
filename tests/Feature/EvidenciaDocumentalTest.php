<?php

namespace Tests\Feature;

use App\Enums\CompetenciaStatus;
use App\Enums\ConferenciaServidorStatus;
use App\Enums\EvidenciaStatus;
use App\Enums\FolhaFrequenciaStatus;
use App\Enums\FrequenciaServidorStatus;
use App\Enums\OrigemInformacaoItem;
use App\Enums\UnidadeLancamento;
use App\Enums\UserRole;
use App\Models\Competencia;
use App\Models\EvidenciaDocumental;
use App\Models\FolhaFrequencia;
use App\Models\FolhaFrequenciaItem;
use App\Models\FolhaFrequenciaServidor;
use App\Models\OcorrenciaFrequencia;
use App\Models\Servidor;
use App\Models\Setor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EvidenciaDocumentalTest extends TestCase
{
    use RefreshDatabase;

    public function test_required_item_document_is_private_reviewable_and_gates_approval(): void
    {
        Storage::fake('local');
        [$folha, $linha, $setorial, $central] = $this->cenarioFrequencia();
        $item = $this->criarItem($linha, true);

        $this->actingAs($setorial)
            ->post(route('frequencia.finalizar', $folha))
            ->assertSessionHasErrors('finalizacao');

        $this->actingAs($setorial)
            ->post(route('frequencia.servidores.itens.evidencias.store', [$folha, $linha, $item]), [
                'arquivo' => UploadedFile::fake()->create('laudo-insalubridade.pdf', 120, 'application/pdf'),
                'descricao' => 'Laudo válido para a competência.',
            ])
            ->assertSessionHasNoErrors();

        $evidencia = EvidenciaDocumental::firstOrFail();
        Storage::disk('local')->assertExists($evidencia->caminho_arquivo);
        $this->assertSame(EvidenciaStatus::PENDENTE, $evidencia->status);
        $this->assertDatabaseHas('audit_logs', [
            'acao' => 'CRIOU',
            'modelo' => 'EvidenciaDocumental',
            'modelo_id' => $evidencia->id,
        ]);
        $this->actingAs($setorial)
            ->get(route('frequencia.show', $folha))
            ->assertOk()
            ->assertSee('laudo-insalubridade.pdf')
            ->assertSee('Aguardando conferência');

        $this->actingAs($setorial)
            ->post(route('frequencia.finalizar', $folha))
            ->assertSessionHasNoErrors();
        $this->assertSame(FolhaFrequenciaStatus::FINALIZADA, $folha->fresh()->status);
        $this->actingAs($central)
            ->get(route('painel-frequencias.show', $folha))
            ->assertOk()
            ->assertSee('laudo-insalubridade.pdf')
            ->assertSee('Aguardando conferência');

        $this->actingAs($central)
            ->post(route('painel-frequencias.servidores.conferir', [$folha, $linha]), [
                'status' => ConferenciaServidorStatus::CONFERIDO->value,
            ])
            ->assertSessionHasErrors('conferencia', null, "conferencia_{$linha->id}");

        $this->actingAs($central)
            ->post(route('painel-frequencias.evidencias.revisar', [$folha, $linha, $evidencia]), [
                'status' => EvidenciaStatus::RECUSADA->value,
                'motivo_revisao' => 'Curto',
            ])
            ->assertSessionHasErrors('motivo_revisao', null, "revisao_evidencia_{$evidencia->id}");

        $this->actingAs($central)
            ->post(route('painel-frequencias.evidencias.revisar', [$folha, $linha, $evidencia]), [
                'status' => EvidenciaStatus::ACEITA->value,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($central)
            ->post(route('painel-frequencias.servidores.conferir', [$folha, $linha]), [
                'status' => ConferenciaServidorStatus::CONFERIDO->value,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($central)
            ->post(route('painel-frequencias.evidencias.revisar', [$folha, $linha, $evidencia]), [
                'status' => EvidenciaStatus::RECUSADA->value,
                'motivo_revisao' => 'O documento enviado não permite confirmar a vigência informada.',
            ])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('conferencias_frequencia_servidor', [
            'folha_frequencia_servidor_id' => $linha->id,
            'rodada' => 1,
        ]);

        $this->actingAs($central)
            ->post(route('painel-frequencias.aprovar', $folha))
            ->assertSessionHasErrors('conferencia');

        $this->actingAs($central)
            ->post(route('painel-frequencias.evidencias.revisar', [$folha, $linha, $evidencia]), [
                'status' => EvidenciaStatus::ACEITA->value,
            ])
            ->assertSessionHasNoErrors();
        $this->actingAs($central)
            ->post(route('painel-frequencias.servidores.conferir', [$folha, $linha]), [
                'status' => ConferenciaServidorStatus::CONFERIDO->value,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($central)
            ->post(route('painel-frequencias.aprovar', $folha))
            ->assertSessionHasNoErrors();
        $this->assertSame(FolhaFrequenciaStatus::APROVADA, $folha->fresh()->status);

        $this->actingAs($central)
            ->get(route('evidencias.download', $evidencia))
            ->assertOk()
            ->assertDownload('laudo-insalubridade.pdf');
        $this->assertDatabaseHas('audit_logs', [
            'acao' => 'LEU',
            'modelo' => 'EvidenciaDocumental',
            'modelo_id' => $evidencia->id,
        ]);
    }

    public function test_occurrence_with_proof_requires_attachment_and_blocks_cross_sector_access(): void
    {
        Storage::fake('local');
        [$folha, $linha, $setorial] = $this->cenarioFrequencia();
        $ocorrencia = OcorrenciaFrequencia::create([
            'servidor_id' => $linha->servidor_id,
            'setor_id' => $folha->setor_id,
            'competencia_id' => $folha->competencia_id,
            'tipo' => 'ATESTADO_MEDICO',
            'possui_comprovacao' => true,
            'origem' => 'MANUAL',
            'criado_por_id' => $setorial->id,
        ]);

        $this->actingAs($setorial)
            ->post(route('frequencia.finalizar', $folha))
            ->assertSessionHasErrors('finalizacao');

        $this->actingAs($setorial)
            ->post(route('ocorrencias.evidencias.store', $ocorrencia), [
                'arquivo' => UploadedFile::fake()->image('atestado.png', 800, 600),
            ])
            ->assertSessionHasNoErrors();

        $evidencia = $ocorrencia->evidencias()->firstOrFail();
        $this->actingAs($setorial)
            ->get(route('ocorrencias.show', $ocorrencia))
            ->assertOk()
            ->assertSee('atestado.png');
        $outroSetor = Setor::create(['nome' => 'Outro setor', 'sigla' => 'OUT', 'ativo' => true]);
        $outroSetorial = $this->criarUsuario($outroSetor, UserRole::SETORIAL, 'outro-setor@example.test');

        $this->actingAs($outroSetorial)
            ->get(route('evidencias.download', $evidencia))
            ->assertForbidden();

        $this->actingAs($setorial)
            ->get(route('evidencias.download', $evidencia))
            ->assertOk()
            ->assertDownload('atestado.png');

        $this->actingAs($setorial)
            ->post(route('frequencia.finalizar', $folha))
            ->assertSessionHasNoErrors();

        $this->actingAs($setorial)
            ->delete(route('evidencias.destroy', $evidencia))
            ->assertForbidden();
    }

    public function test_upload_validation_and_removal_preserve_audit_without_public_file(): void
    {
        Storage::fake('local');
        [$folha, $linha, $setorial] = $this->cenarioFrequencia();
        $item = $this->criarItem($linha, false);

        $this->actingAs($setorial)
            ->post(route('frequencia.servidores.itens.evidencias.store', [$folha, $linha, $item]), [
                'arquivo' => UploadedFile::fake()->create('programa.exe', 20, 'application/x-msdownload'),
            ])
            ->assertSessionHasErrors('arquivo', null, "evidencia_folha_frequencia_itens_{$item->id}");
        $this->assertDatabaseCount('evidencias_documentais', 0);

        $this->actingAs($setorial)
            ->post(route('frequencia.servidores.itens.evidencias.store', [$folha, $linha, $item]), [
                'arquivo' => UploadedFile::fake()->create('comprovante.pdf', 30, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors();

        $evidencia = EvidenciaDocumental::firstOrFail();
        $caminho = $evidencia->caminho_arquivo;
        $this->assertStringNotContainsString('public', $caminho);
        Storage::disk('local')->put($caminho, 'conteúdo adulterado');

        $this->actingAs($setorial)
            ->get(route('evidencias.download', $evidencia))
            ->assertSessionHasErrors('evidencia');

        $this->actingAs($setorial)
            ->delete(route('evidencias.destroy', $evidencia))
            ->assertSessionHasNoErrors();

        Storage::disk('local')->assertMissing($caminho);
        $this->assertSoftDeleted('evidencias_documentais', ['id' => $evidencia->id]);
        $this->assertDatabaseHas('audit_logs', [
            'acao' => 'EXCLUIU',
            'modelo' => 'EvidenciaDocumental',
            'modelo_id' => $evidencia->id,
        ]);
    }

    /** @return array{FolhaFrequencia, FolhaFrequenciaServidor, User, User} */
    private function cenarioFrequencia(): array
    {
        $setor = Setor::create(['nome' => 'Setor de evidências', 'sigla' => 'EVD', 'ativo' => true]);
        $setorial = $this->criarUsuario($setor, UserRole::SETORIAL, 'setorial-evidencia@example.test');
        $central = $this->criarUsuario($setor, UserRole::CENTRAL, 'central-evidencia@example.test');
        $competencia = Competencia::create([
            'referencia' => '2026-07',
            'status' => CompetenciaStatus::ABERTA,
            'aberta_por' => $central->id,
        ]);
        $servidor = Servidor::create([
            'matricula' => 'MAT-EVD-001',
            'nome' => 'Servidor com evidência',
            'setor_id' => $setor->id,
            'origem_registro' => 'MANUAL',
            'ativo' => true,
        ]);
        $folha = FolhaFrequencia::create([
            'setor_id' => $setor->id,
            'competencia_id' => $competencia->id,
            'status' => FolhaFrequenciaStatus::RASCUNHO,
            'criado_por_id' => $setorial->id,
        ]);
        $linha = $folha->servidores()->create([
            'servidor_id' => $servidor->id,
            'matricula' => $servidor->matricula,
            'nome' => $servidor->nome,
            'status' => FrequenciaServidorStatus::INTEGRAL,
            'atualizado_por_id' => $setorial->id,
            'preenchida_em' => now(),
        ]);

        return [$folha, $linha, $setorial, $central];
    }

    private function criarItem(FolhaFrequenciaServidor $linha, bool $exigeDocumento): FolhaFrequenciaItem
    {
        return $linha->itens()->create([
            'codigo_evento' => 'DOC-001',
            'sigla' => 'DOC',
            'descricao' => 'Item documental',
            'unidade_lancamento' => UnidadeLancamento::MARCADOR,
            'origem_informacao' => OrigemInformacaoItem::SETOR_MENSAL,
            'exige_documento' => $exigeDocumento,
            'editavel_pelo_setor' => true,
        ]);
    }

    private function criarUsuario(Setor $setor, UserRole $role, string $email): User
    {
        return User::create([
            'name' => $role->label(),
            'email' => $email,
            'password' => 'senha-segura',
            'setor_id' => $setor->id,
            'role' => $role,
        ]);
    }
}
