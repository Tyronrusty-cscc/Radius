<?php namespace App\Controllers;

use App\Models\UserModel;



class Users extends BaseController
{

	protected $model; // Declare UserModel as a class property

    public function __construct(UserModel $userModel = null)
    {
        // Assign the model, allow injection for testing
        $this->model = $userModel ?? new UserModel();
    }

    public function index()
    {
        $data =[];
        helper(['form']);

		if ($this->request->getMethod() == 'post') {
			//let's do the validation here
			$rules = [
				'email' => 'required|min_length[6]|max_length[50]|valid_email',
				'password' => 'required|min_length[8]|max_length[255]|validateUser[email,password]',
			];

			$errors = [
				'password' => [
					'validateUser' => 'Email or Password don\'t match'
				]
			];

			if (! $this->validate($rules, $errors)) {
	
				$data['validation'] = $this->validator;
				$this->response->setStatusCode(400);

			}else{
			

				$user = $model->where('email', $this->request->getVar('email'))
											->first();

				$this->setUserSession($user);
				//$session->setFlashdata('success', 'Successful Registration');
				$this->response->setStatusCode(200);

				return redirect()->to('/dashboard');

			}
		} 

        echo view('templates/header',$data);
        echo view('login');
        echo view('templates/footer');
        
		
    }
	private function setUserSession($user){
		$data = [
		  'id' => $user['id'],
		  'firstname' => $user['firstName'],
		  'lastname' => $user['lastName'],
		   'email' => $user['email'],
		   'isLoggedIn' => true,
		];

		session()->set($data);
		return true;
	}

    public function register(){
		$data = [];
		helper(['form']);

		if ($this->request->is('post')) {
			//let's do the validation here
			$rules = [
				'firstname' => 'required|min_length[3]|max_length[20]',
				'lastname' => 'required|min_length[3]|max_length[20]',
                'email' => 'required|min_length[6]|max_length[50]|valid_email|is_unique[users.email]',
				'password' => 'required|min_length[8]|max_length[255]',
				'password_confirm' => 'matches[password]',
			];
            
			if (! $this->validate($rules)) {
				$data['validation'] = $this->validator;
				 $this->response->setStatusCode(400);
			
			}else{
				

				$newData = [
					'firstname' => $this->request->getVar('firstname'),
					'lastname' => $this->request->getVar('lastname'),
					'email' => $this->request->getVar('email'),
					'password' => $this->request->getVar('password'),
				];
				$this->model->save($newData);
				$session = session();
				$session->setFlashdata('success', 'Successful Registration');
				$this->response->setStatusCode(201);

				return redirect()->to('/');

			}
		}


		echo view('templates/header', $data);
		echo view('register');
		echo view('templates/footer');
	}
}
