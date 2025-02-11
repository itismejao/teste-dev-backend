<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class VacancyController extends Controller
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
            $filterColumn = $request->filterCollumn ?? 'name';
            $filter = $request->filter ?? '';
            $orderBy = $request->orderBy ?? 'id';
            $orderDirection = $request->orderDirection ?? 'asc';

            $vacancys = Cache::tags('vacancys')->remember("$perPage$filterColumn$filter$orderBy$orderDirection", now()->addMinutes(60),function () use ($filterColumn,$filter,$orderBy, $orderDirection,$perPage){
                return Vacancy::where($filterColumn, 'LIKE', '%' . $filter . '%')->orderBy($orderBy, $orderDirection)->paginate($perPage);
            });

            if($vacancys->isEmpty()){
                return response()->json([
                    'error' => true,
                    'message' => "Nenhuma vaga encontrada com os filtros especificados."
                ],200);
            }

            return response()->json($vacancys);

        } catch (\Throwable $th) {
            return response()->json([
                'error' => true,
                'message' => "Ocorreu uma falha ao tentar requisitar as vagas. <br> Por favor, verifique a documentação ou entre em contato com o suporte."
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

            if(auth()->user()->user_type == 'recrutador'){
                $validator = Validator::make($request->all(), [
                    'name'=>'required|string|max:255',
                    'description'=>'required|string',
                    'vacancy_type'=>'required|string|in:clt,pj,freelancer',
                    'user_id'=>'required|integer',
                    'opened' => 'boolean'
                ],
                ['vacancy_type.in' => 'Given data must be clt,pj ou freelancer']
                );
                
                if ($validator->fails()) {
                    return response()->json([
                        'error' => true,
                        'message' => 'validation error',
                        'errors' => $validator->errors()
                    ], 400);
                }

                $user = User::find($request->get('user_id'));

                if($user){
                    if($user->user_type!="recrutador"){
                        return response()->json([
                            'error' => true,
                            'message' => "O usuário responsável pela vaga informado deve ser um recrutador."
                        ],400);
                    }
                } else {
                    return response()->json([
                        'error' => true,
                        'message' => "O usuário responsável pela vaga informado é inexistente."
                    ],400);
                }
        
                $vacancy = new Vacancy([
                    'name' => $request->get('name'),
                    'description' => $request->get('description'),
                    'vacancy_type' => $request->get('vacancy_type'),
                    'user_id' => $request->get('user_id') ?? auth()->user()->id,
                    'opened' => $request->get('opened') ?? 1,
                ]);
        
                $vacancy->save();

                Cache::tags('vacancys')->flush();

                return [
                    'success' => true,
                    'message' => "Vaga cadastrada com sucesso."
                ];
            } else {
                return response()->json([
                    'error' => true,
                    'message' => "Usuário autenticado não pode cadastrar uma nova vaga pois não é recrutador"
                ],401);
            }
    
        } catch (\Throwable $th) {
            return response()->json([
                'error' => true,
                'message' => "Ocorreu uma falha ao tentar cadastrar a vaga. <br> Por favor, verifique a documentação ou entre em contato com o suporte."
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

            $vacancy = Cache::remember("vacancy".$id, now()->addMinutes(60),function () use ($id){ 
                return Vacancy::find($id);
            });

            if(!$vacancy){
                return response()->json([
                    'error' => true,
                    'message' => "Nenhuma vaga encontrada com o ID especificado."
                ],200);
            }

            return response()->json($vacancy);

        } catch (\Throwable $th) {
            return response()->json([
                'error' => true,
                'message' => "Ocorreu uma falha ao tentar requisitar a vaga. <br> Por favor, verifique a documentação ou entre em contato com o suporte."
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
                'name'=>'required|string|max:255',
                'description'=>'required|string',
                'vacancy_type'=>'required|string|in:clt,pj,freelancer',
                'user_id'=>'required|integer',
                'opened' => 'boolean'
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
                if($user->user_type!="recrutador"){
                    return response()->json([
                        'error' => true,
                        'message' => "O usuário responsável pela vaga informado deve ser um recrutador."
                    ],400);
                }
            } else {
                return response()->json([
                    'error' => true,
                    'message' => "O usuário responsável pela vaga informado é inexistente."
                ],400);
            }

            $vacancy = Vacancy::find($id);

            if(auth()->user()->id == $vacancy->user_id){

                if(!$vacancy){
                    return response()->json([
                        'error' => true,
                        'message' => "Nenhuma vaga encontrada com o ID especificado."
                    ],400);
                }

                $vacancy->name = $request->get('name');
                $vacancy->description = $request->get('description');
                $vacancy->vacancy_type = $request->get('vacancy_type');
                $vacancy->user_id = $request->get('user_id');
                $vacancy->opened = $request->get('opened') ?? 1;

                $vacancy->save();

                Cache::forget("vacancy$id");
                Cache::tags('vacancys')->flush();

                return [
                    'success' => true,
                    'message' => "Vaga atualizada com sucesso."
                ];
            } else {
                return response()->json([
                    'error' => true,
                    'message' => "Usuário autenticado não pode alterar a vaga pois não é o recrutador responsável pela vaga"
                ],401);
            }
    
        } catch (\Throwable $th) {
            return response()->json([
                'error' => true,
                'message' => "Ocorreu uma falha ao tentar atualizar a vaga. <br> Por favor, verifique a documentação ou entre em contato com o suporte."
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
           

            $vacancy = Vacancy::find($id);

            if(!$vacancy){
                return response()->json([
                    'error' => true,
                    'message' => "Nenhuma vaga encontrada com o ID especificado."
                ],200);
            }

            if(auth()->user()->id == $vacancy->user_id){

                $vacancy->delete();

                Cache::forget("vacancy$id");
                Cache::tags('vacancys')->flush();

                return [
                    'success' => true,
                    'message' => "Vaga removida com sucesso."
                ];
            } else {
                return response()->json([
                    'error' => true,
                    'message' => "Usuário autenticado não pode remover a vaga pois não é o recrutador responsável pela vaga"
                ],401);
            }


        } catch (\Throwable $th) {
            return response()->json([
                'error' => true,
                'message' => "Ocorreu uma falha ao tentar remover a vaga. <br> Por favor, verifique a documentação ou entre em contato com o suporte."
                //'message' => $th->getMessage()
            ],400);
        }
    }

    public function pause($id){
        try {

            $vacancy = Vacancy::find($id);

            if(!$vacancy){
                return response()->json([
                    'error' => true,
                    'message' => "Nenhuma vaga encontrada com o ID especificado."
                ],200);
            }

            if(auth()->user()->id == $vacancy->user_id){

                $vacancy->opened = 0;

                $vacancy->save();

                return [
                    'success' => true,
                    'message' => "Vaga pausada com sucesso."
                ];
            } else {
                return response()->json([
                    'error' => true,
                    'message' => "Usuário autenticado não pode pausar a vaga pois não é o recrutador responsável pela vaga"
                ],401);
            }

        } catch (\Throwable $th) {
            return response()->json([
                'error' => true,
                'message' => "Ocorreu uma falha ao tentar pausar a vaga. <br> Por favor, verifique a documentação ou entre em contato com o suporte."
                //'message' => $th->getMessage()
            ],400);
        }
    }

    /**
     * Remove multiples specified resources from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function batchDestroy(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'ids'=>'required|array',
                'ids.*'=>'sometimes|integer',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => true,
                    'message' => 'validation error',
                    'errors' => $validator->errors()
                ], 400);
            }

            if(auth()->user()->user_type == 'recrutador'){

                $ids = $request->get('ids');

                DB::beginTransaction();

                foreach ($ids as $id) {

                    $vacancy = Vacancy::find($id);

                    if(!$vacancy){
                        return response()->json([
                            'error' => true,
                            'message' => "Nenhuma vaga encontrada com o ID $id especificado. Operação em lote cancelada!"
                        ],400);
                        DB::rollback();
                    }

                    if(auth()->user()->id != $vacancy->user_id){
                        return response()->json([
                            'error' => true,
                            'message' => "Usuário autenticado não pode remover a vaga de ID $id pois não é o recrutador responsável pela vaga. Operação em lote cancelada!"
                        ],401);
                        DB::rollback();
                    }

                    $vacancy->delete();
        
                    Cache::forget("vacancy$id");

                }

                Cache::tags('vacancys')->flush();

            } else {
                return response()->json([
                    'error' => true,
                    'message' => "Exclusão de vagas em lote só pode ser realizar por um recrutador"
                ],401);
            }

            DB::commit();

            return [
                'success' => true,
                'message' => "Vagas removidas com sucesso.",
                'ids_removed' => $ids
            ];

        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                'error' => true,
                'message' => "Ocorreu uma falha ao tentar remover as vagas. <br> Por favor, verifique a documentação ou entre em contato com o suporte."
                //'message' => $th->getMessage()
            ],400);
        }
    }
}
