<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller,Request,Response,Csrf};
use App\Models\FacialEnrollment;

final class FacialEnrollmentController extends Controller
{
    public function employees(Request $request): Response
    {
        $query = trim((string)$request->query('q', ''));
        if (mb_strlen($query) < 2) {
            return Response::json(['success'=>true,'employees'=>[]]);
        }

        try {
            $employees = (new FacialEnrollment())->searchEmployees($query);
            return Response::json(['success'=>true,'employees'=>$employees]);
        } catch (\Throwable $e) {
            error_log('[FACIAL] Busca de funcionários: '.$e->getMessage());
            return Response::json([
                'success'=>false,
                'message'=>'Não foi possível localizar os funcionários. Verifique a conexão com o banco RM e a tabela de cadastro facial.'
            ], 500);
        }
    }

    public function register(Request $request): Response
    {
        $token = $request->input('_token') ?? $request->header('X-CSRF-TOKEN');
        if (!Csrf::verify(is_string($token) ? $token : null)) {
            return Response::json(['success'=>false,'message'=>'Token de segurança inválido. Atualize a página e tente novamente.'],419);
        }

        $codColigada = (int)$request->input('codcoligada', 0);
        $chapa = trim((string)$request->input('chapa', ''));
        $raw = (string)$request->input('descriptors', '');
        $descriptors = json_decode($raw, true);

        if ($codColigada <= 0 || $chapa === '') {
            return Response::json(['success'=>false,'message'=>'Funcionário inválido.'],422);
        }
        if (!is_array($descriptors) || count($descriptors) < 2 || count($descriptors) > 3) {
            return Response::json(['success'=>false,'message'=>'É necessário realizar de 2 a 3 capturas faciais.'],422);
        }

        $binaries = [];
        foreach ($descriptors as $descriptor) {
            if (!is_string($descriptor)) {
                return Response::json(['success'=>false,'message'=>'Captura facial inválida.'],422);
            }
            $binary = base64_decode($descriptor, true);
            if ($binary === false || strlen($binary) !== 512) {
                return Response::json(['success'=>false,'message'=>'Uma das capturas faciais é inválida.'],422);
            }
            $binaries[] = $binary;
        }

        try {
            $model = new FacialEnrollment();
            $employee = $model->findEligibleEmployee($codColigada, $chapa);
            if (!$employee || $employee['CPF'] === '') {
                return Response::json(['success'=>false,'message'=>'Funcionário não é elegível ou não possui CPF cadastrado.'],422);
            }

            $id = $model->save($employee, $binaries);
            return Response::json([
                'success'=>true,
                'id'=>$id,
                'employee'=>$employee['NOMEFUNCIONARIO'],
                'message'=>'Cadastro facial concluído com sucesso.'
            ]);
        } catch (\Throwable $e) {
            error_log('[FACIAL] Gravação do cadastro facial: '.$e->getMessage());
            return Response::json(['success'=>false,'message'=>'Não foi possível salvar o cadastro facial no SQL Server. Verifique a conexão e a tabela dbo.PONTO_FACIAL.'],500);
        }
    }

    public function delete(Request $request): Response
    {
        $token = $request->input('_token') ?? $request->header('X-CSRF-TOKEN');
        if (!Csrf::verify(is_string($token) ? $token : null)) {
            return Response::json(['success'=>false,'message'=>'Token de segurança inválido. Atualize a página e tente novamente.'],419);
        }

        $codColigada = (int)$request->input('codcoligada', 0);
        $chapa = trim((string)$request->input('chapa', ''));
        if ($codColigada <= 0 || $chapa === '') {
            return Response::json(['success'=>false,'message'=>'Funcionário inválido.'],422);
        }

        try {
            $deleted = (new FacialEnrollment())->delete($codColigada, $chapa);
            if (!$deleted) {
                return Response::json(['success'=>false,'message'=>'Não existe cadastro facial ativo para este funcionário.'],404);
            }
            return Response::json([
                'success'=>true,
                'message'=>'Cadastro facial excluído com sucesso. Os descritores biométricos foram removidos.',
            ]);
        } catch (\Throwable $e) {
            error_log('[FACIAL] Exclusão do cadastro facial: '.$e->getMessage());
            return Response::json(['success'=>false,'message'=>'Não foi possível excluir o cadastro facial. Verifique o log do servidor.'],500);
        }
    }
}
