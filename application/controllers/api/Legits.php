<?php

defined('BASEPATH') OR exit('No direct script access allowed');

use chriskacerguis\RestServer\RestController;
use SebastianBergmann\Environment\Console;
use LasseRafn\Initials\Initials;

class Legits extends RestController {

    protected $user_detail;
    function __construct()
    {
        parent::__construct();
        $this->load->library('Authorization_Token');
        $this->load->helper(array('user_helper'));
        $this->load->model('User_model','user');
        $this->load->model('LegitImage_model','image');
        $this->load->model('Legit_model','legit');
        $this->load->model('LegitDetail_model','legit_detail');
        $this->load->model('Payment_model','payment');
        $this->load->model('Validator_model','validator');
    }
    function caseCode($user_id,$brand_id){
        $data_user = $this->user->get_by(array('id' => $user_id),'','',true,array('nama','user_code'));
        $no_prodile = $data_user->user_code ;
        $jumlah_produk_check = $this->legit->count(array('user_id' => $user_id));
        $inisial_profile = (new Initials)->generate($data_user->nama);;
        $nomor_jenis_barnag = $brand_id;
        $code = $no_prodile .'-'.$jumlah_produk_check.$inisial_profile.$nomor_jenis_barnag.'X';
        return $code;
    }
    public function savelegit_post()
	{   
        $this->authorization_token->authtoken();
        // $this->authorization_token->authtoken();
        // $headers = $this->input->request_headers();
        // $datauser = $this->authorization_token->validateToken($headers['Authorization']);
        // $this->response($datauser('user_id'));
        // var_dump($datauser);
        try {
            $kategori_id = $this->input->post('kategori');
            $brand_id = $this->input->post('brand');
            $nama_item = $this->input->post('nama_item');
            $catatan = $this->input->post('catatan');
            $data_foto = $this->input->post('data_foto');
            $user_id = $this->input->post('user_id');
            $this->form_validation->set_rules('kategori', 'Kategori', 'required');
            $this->form_validation->set_rules('brand', 'Brand', 'required');
            $this->form_validation->set_rules('nama_item', 'Nama Item', 'required');
            $this->form_validation->set_message('required', '{field} tidak boleh kosong!');
            $this->form_validation->set_error_delimiters('', '');
            // if(!$this->form_validation->run()) throw new Exception(validation_errors());
            
            $data = array(
                'user_id'   => $user_id,
                'kategori'      => $kategori_id,
                'brand'  => $brand_id,
                'nama_item'  => $nama_item,
                'data_foto' => $data_foto,
                'catatan'     => $catatan,
            );
            // $response = [
            //     'status'    => false,
            //     'message'   => ''
            // ];
            $case_id = $this->caseCode($user_id,$brand_id);
            $this->db->trans_begin();
            $data_legit = array(
                'case_code' => $case_id,
                'user_id'   => $user_id,
                'legit_status'  => 'posted',
                'submit_time'   => date('Y-m-d H:i:s'),
                'created_at'    => date('Y-m-d H:i:s')
            );
            $legit_id = $this->legit->insert($data_legit);
            $data_legit_detail = array(
                'legit_id'      => $legit_id,
                'kategori_id'   => $kategori_id,
                'brand_id'      => $brand_id,
                'nama_item'     => $nama_item,
                'catatan'       => $catatan,
                'created_at'    => date('Y-m-d H:i:s')
            );
            $this->legit_detail->insert($data_legit_detail);
            foreach ($data_foto as $key => $value) {
                $data_gambar_legit = array(
                    'legit_id'  =>$legit_id,
                    'file_path' => $value['nama_foto'],
                    'created_at'    => date('Y-m-d H:i:s')
                );
                $this->image->insert($data_gambar_legit);
            }
            $data_payment = array(
                'legit_id'      => $legit_id,
                'payment_type'  => 'free',
                'payment_status'=> 'lunas',
                'payment_total' => '0',
                'created_at'    => date('Y-m-d H:i:s')
            );
            $this->payment->insert($data_payment);
            if ($this->db->trans_status() === FALSE){
                $this->db->trans_rollback();
                $this->response([
                    'status'    => false,
                    'message'   => 'Terjadi kesalahan'
                ],400);
            }else{
                $this->db->trans_commit();
                $this->response([
                    'status'    => true,
                    'case_id'   => $case_id,
                    'message'   => 'Berhasil'
                ],200);
            }
                // $this->response($data);
            // var_dump($data);
            // $register = $this->user->createUser($data);
            // if($legit_id){
            // }else{
            //     throw new Exception('Register Fail!');
            // }

        } catch (\Throwable $th) {
            $this->response([
                'status' => false,
                'message'   => $th->getMessage(),
            ],400);
        }
	}

    public function data_get(){
        $this->authorization_token->authtoken();
        $headers = $this->input->request_headers();
        $decodedToken = $this->authorization_token->validateToken($headers['Authorization']);

        $user_id = $decodedToken['data']->user_id;
        $dataLegit = $this->legit->getLegitListUser($user_id);
        if($dataLegit){
            foreach ($dataLegit as $key) {
                if($key->check_result == 'preview'){
                    $key->check_result = 'Checking';
                }elseif($key->check_result == 'real'){
                    $key->check_result = 'Original';
                }
                if($key->check_result == null){
                    $key->check_result = 'Process';
                }
            }
            $this->response([
                'status' => true,
                'data'  => $dataLegit
            ],200);
        }else{
            $this->response([
                'status' => false,
                'message' => 'User Not Found!'
            ],404);
        }
    }

    public function datadetail_get(){
        $this->authorization_token->authtoken();
        $headers = $this->input->request_headers();
        $decodedToken = $this->authorization_token->validateToken($headers['Authorization']);

        $case_code = $this->get('case_code');
        $user_id = $decodedToken['data']->user_id;
        $dataLegit = $this->legit->getLegitListUserDetail($user_id,$case_code);
        if($dataLegit){
            foreach ($dataLegit as $key) {
                if($key->check_result == 'preview'){
                    $key->check_result = 'Checking';
                }elseif($key->check_result == 'real'){
                    $key->check_result = 'Original';
                }
                if($key->check_result == null){
                    $key->check_result = 'Process';
                }
                $key->image_list = $this->image->get_by(array('legit_id'=>$key->id),'','','',array('file_path'));
                $key->authentic_comment = $this->validator->validator_data_checker($key->id);
            }
            $this->response([
                'status' => true,
                'data'  => $dataLegit,
            ],200);
        }else{
            $this->response([
                'status' => false,
                'message' => 'Case Code Not Found!'
            ],404);
        }
    }

    public function validatordo_get(){
        $this->authorization_token->authtoken();
        $headers = $this->input->request_headers();
        $decodedToken = $this->authorization_token->validateToken($headers['Authorization']);
        $tipe = $this->get('tipe');
        $dataLegit = $this->legit->getLegitListByStatus($decodedToken['data']->validator_brand_id,$tipe);
        foreach ($dataLegit as $key) {
            if($key->check_result == 'preview'){
                $key->check_result = 'Checking';
            }elseif($key->check_result == 'real'){
                $key->check_result = 'Original';
            }
            if($key->check_result == null){
                $key->check_result = 'Process';
            }
        }
        $this->response([
            'status' => true,
            'data'  => $dataLegit,
        ],200);
    }

    public function validdatadetail_get(){
        $this->authorization_token->authtoken();

        $case_code = $this->get('case_code');
        $dataLegit = $this->legit->getValidateDetail($case_code);
        if($dataLegit){
            foreach ($dataLegit as $key) {
                if($key->check_result == 'preview'){
                    $key->check_result = 'Checking';
                }elseif($key->check_result == 'real'){
                    $key->check_result = 'Original';
                }
                if($key->check_result == null){
                    $key->check_result = 'Process';
                }
                $key->image_list = $this->image->get_by(array('legit_id'=>$key->id),'','','',array('file_path'));
                if(!empty($key->validator_user_id)){
                    $key->authentic_comment = $this->validator->validator_data_single($key->id);
                }else{
                    $key->authentic_comment = [];
                }
            }
            $this->response([
                'status' => true,
                'data'  => $dataLegit,
            ],200);
        }else{
            $this->response([
                'status' => false,
                'message' => 'Case Code Not Found!'
            ],404);
        }
    }

    public function validation_post(){
        $this->authorization_token->authtoken();
        $check_result = $this->input->post('check_result');
        $check_note = $this->input->post('check_note');
        $data = array(
            'legit_id'  =>$this->input->post('legit_id'),
            'check_result'=> $check_result,
            'check_note'  => $check_note,
            'validator_user_id' => $this->input->post('validator_user_id'),
            'final_time_check' =>date('Y-m-d H:i:s')
        );
        $register = $this->validator->insert($data);
        if($register){
            $this->response([
                'status' => true,
                'message'   => 'Hasil validasi disimpan',
                'data'  => []
            ],200);
        }else{
            $this->response([
                'status' => true,
                'message'   => 'Terjadi kesalahan',
            ],403);
        }
    }

    public function summaryadmin_get(){
        $this->authorization_token->authtoken();
        $data = array(
            'total_user'  => $this->user->count(array('role' => 'user')),
            'total_validator'=> $this->user->count(array('role' => 'validator')),
            'total_legit_success'  => '...',
        );
        $this->response([
            'status' => true,
            'data'  => $data
        ],200);
    }

    

}