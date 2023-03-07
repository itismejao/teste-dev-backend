<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class ApplicationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {

            $perPage = $request->perPage ?? 20;
            $filterColumn = $request->filterCollumn ?? 'vacancy_id';
            $filter = $request->filter ?? '';
            $orderBy = $request->orderBy ?? 'id';
            $orderDirection = $request->orderDirection ?? 'asc';

            $applications = Cache::remember('applications2', now()->addMinutes(60),function () use ($filterColumn,$filter,$orderBy, $orderDirection,$perPage){
                return Application::with(['vacancy','user'])->where($filterColumn, 'LIKE', '%' . $filter . '%')->orderBy($orderBy, $orderDirection)->paginate($perPage);
            });

            if($applications->isEmpty()){
                return response()->json([
                    'error' => true,
                    'message' => "Nenhuma candidatura encontrada com os filtros especificados."
                ],200);
            }

            return response()->json($applications);

        } catch (\Throwable $th) {
            return response()->json([
                'error' => true,
                'message' => "Ocorreu uma falha ao tentar requisitar as candidaturas. <br> Por favor, verifique a documentação ou entre em contato com o suporte."
                // 'message' => $th->getMessage()
            ],400);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'vacancy_id'=>'required|integer',
                'user_id'=>'required|integer',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => true,
                    'message' => 'validation error',
                    'errors' => $validator->errors()
                ], 400);
            }

            $user = User::find($request->get('user_id'));

            if($user){
                if($user->user_type!="candidato"){
                    return response()->json([
                        'error' => true,
                        'message' => "O usuário deve ser um candidato."
                    ],400);
                }
            } else {
                return response()->json([
                    'error' => true,
                    'message' => "O usuário informado é inexistente."
                ],400);
            }

            $vacancy = Vacancy::find($request->get('vacancy_id'));

            if($vacancy){
                if($vacancy->opened!=1){
                    return response()->json([
                        'error' => true,
                        'message' => "A vaga não aceita mais candidaturas."
                    ],400);
                }
            } else {
                return response()->json([
                    'error' => true,
                    'message' => "A vaga informada é inexistente."
                ],400);
            }
    
            $application = new Application([
                'vacancy_id' => $request->get('vacancy_id'),
                'user_id' => $request->get('user_id'),
            ]);
    
            $application->save();

            return [
                'success' => true,
                'message' => "Candidatura cadastrada com sucesso."
            ];
    
        } catch (\Throwable $th) {
            return response()->json([
                'error' => true,
                'message' => "Ocorreu uma falha ao tentar cadastrar a candidatura. <br> Por favor, verifique a documentação ou entre em contato com o suporte."
                // 'message' => $th->getMessage()
            ],400);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try {

            $application = Application::with(['vacancy','user'])->find($id);

            if(!$application){
                return response()->json([
                    'error' => true,
                    'message' => "Nenhuma candidatura encontrada com o ID especificado."
                ],200);
            }

            return response()->json($application);

        } catch (\Throwable $th) {
            return response()->json([
                'error' => true,
                'message' => "Ocorreu uma falha ao tentar requisitar a candidatura. <br> Por favor, verifique a documentação ou entre em contato com o suporte."
                // 'message' => $th->getMessage()
            ],400);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'vacancy_id'=>'required|integer',
                'user_id'=>'required|integer',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => true,
                    'message' => 'validation error',
                    'errors' => $validator->errors()
                ], 400);
            }

            $user = User::find($request->get('user_id'));

            if($user){
                if($user->user_type!="candidato"){
                    return response()->json([
                        'error' => true,
                        'message' => "O usuário deve ser um candidato."
                    ],400);
                }
            } else {
                return response()->json([
                    'error' => true,
                    'message' => "O usuário informado é inexistente."
                ],400);
            }

            $vacancy = Vacancy::find($request->get('vacancy_id'));

            if($vacancy){
                if($vacancy->opened!=1){
                    return response()->json([
                        'error' => true,
                        'message' => "A vaga não aceita mais candidaturas."
                    ],400);
                }
            } else {
                return response()->json([
                    'error' => true,
                    'message' => "A vaga informada é inexistente."
                ],400);
            }

            $application = Application::find($id);

            if(!$application){
                return response()->json([
                    'error' => true,
                    'message' => "Nenhuma candidatura encontrada com o ID especificado."
                ],200);
            }
    
            $application->vacancy_id = $request->get('vacancy_id');
            $application->user_id = $request->get('user_id');
    
            $application->save();

            return [
                'success' => true,
                'message' => "Candidatura atualizada com sucesso."
            ];
    
        } catch (\Throwable $th) {
            return response()->json([
                'error' => true,
                'message' => "Ocorreu uma falha ao tentar atualizar a candidatura. <br> Por favor, verifique a documentação ou entre em contato com o suporte."
                // 'message' => $th->getMessage()
            ],400);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {

            $application = Application::find($id);

            if(!$application){
                return response()->json([
                    'error' => true,
                    'message' => "Nenhuma candidatura encontrada com o ID especificado."
                ],200);
            }

            $application->delete();

            return [
                'success' => true,
                'message' => "Candidatura removida com sucesso."
            ];

        } catch (\Throwable $th) {
            return response()->json([
                'error' => true,
                'message' => "Ocorreu uma falha ao tentar remover a candidatura. <br> Por favor, verifique a documentação ou entre em contato com o suporte."
                //'message' => $th->getMessage()
            ],400);
        }
    }
}