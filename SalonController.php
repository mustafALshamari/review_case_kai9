<?php

namespace App\Http\Controllers\Stylist;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Notifications\SalonInvitation;
use Illuminate\Support\Str;
use App\Invitation;
use App\SalonWorkTime;
use App\SalonMenu;
use App\SalonEmployee;
use App\User;
use App\Salon;
use App\Stylist;
use App\Services;
use Validator;
use Exception;
use DB;
use Auth;
use File;

class SalonController extends Controller
{
    protected $workingTimes;

    /**
     * @SWG\Post(
     *     path="/api/stylist/add_salon",
     *     summary="create salon",
     *     tags={"Salon"},
     *     description="create salon and if exist , then you can update it using this API",
     *     security={{"passport": {}}},
     *     @SWG\Parameter(
     *         name="name",
     *         in="path",
     *         description="salon's name",
     *         required=true,
     *         type="string",
     *     ),
     *     @SWG\Parameter(
     *         name="location",
     *         in="path",
     *         description="location",
     *         required=false,
     *         type="string",
     *     ),
     *      @SWG\Parameter(
     *         name="latitude",
     *         in="path",
     *         description="latitude",
     *         required=false,
     *         type="number",
     *     ),
     *     @SWG\Parameter(
     *         name="longitude",
     *         in="path",
     *         description="longitude",
     *         required=false,
     *         type="number",
     *     ),
     *      @SWG\Parameter(
     *         name="images[]",
     *         in="path",
     *         description="images max 10 pieces",
     *         required=false,
     *         type="string",
     *     ),
     *    @SWG\Parameter(
     *         name="service_name[]",
     *         in="path",
     *         description="service_name",
     *         required=false,
     *         type="string",
     *     ),
     *    @SWG\Parameter(
     *         name="item[]",
     *         in="path",
     *         description="item array has item_name and item_price",
     *         required=false,
     *         type="string",
     *     ),
     *    @SWG\Parameter(
     *         name="working_days[]",
     *         in="path",
     *         description="working_days and time object",
     *         required=false,
     *         type="string",
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="successful operation message ",
     *     ),
     *     @SWG\Response(
     *         response="422",
     *         description="validation error",
     *     ),
     *     @SWG\Response(
     *         response="500",
     *         description="error something went wrong",
     *     ),
     * )
     */
    public function addSalon(Request $request)
    {
       $validator =  Validator::make(
           $request->all() ,[
            'name'              => 'required',
            'location'          => '',
            'images.*'          => 'mimes:jpg,jpeg,gif,png',
            'service_name'      => 'array',
            'item'              => 'array',
        ]);

        try {
            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }

            $stylist = $this->findStylistById(Auth::id());
            $salon   = Salon::find($stylist->salon_id);

            if ($salon) {

                return response()->json(['warnning' => 'you can not create more than 1 salon']
                                        , 422);
            } else {
                $salon = new Salon();

                if ($request->name) {
                    $salon->name = $request->name;
                }

                if ($request->location) {
                    foreach ($request->location as $key => $value) {
                        $salon->address   = $value['address'];
                        $salon->latitude  = $value['latitude'];
                        $salon->longitude = $value['longitude'];
                    }
                }

                if ($request->hasFile('images')) {
                    foreach ($request->file('images') as $file) {
                        $name   = time().'.'.$file->getClientOriginalName();
                        $file->move(public_path().'/uploads/salon/'. Auth::id() , $name);
                        $data[] = $name;
                    }
                    $salon->images = json_encode($data);
                }
            }

            $salon->save();

            $stylist           = $this->findStylistById(Auth::id());
            $stylist->salon_id = $salon->id;
            $stylist->save();

            if ($request->service_name) {
                foreach($request->service_name as $service) {
                    $serviceModel = new Services();
                    $serviceModel->name = $service;
                    $serviceModel->salon_id = $salon->id;
                    $serviceModel->save();
               }
            }

           $services = Salon::find($salon->id)->service;

           if ($request->item) {
                foreach ( $request->item as $value){
                    $dataForMenu = [
                        'item_name'  => $value['item_name'],
                        'item_price' => $value['item_price'] ,
                        'salon_id'   => $salon->id
                    ];
                    $menu =  SalonMenu::insert($dataForMenu);
                }
            }

           $workModel = new SalonWorkTime();
           $this->workingDaysCreate($workModel ,$request, $salon->id);

            return response()->json(['success'        => 'successfully created your salon',
                                      'salon'         => $salon,
                                      'services'      => $services,
                                      'salon_time'    => $this->workingTimes],
                                      200);
        } catch (Exception $e) {
            return response()->json(['error' => 'something went wrong!'], 500);
        }
    }

    /**
     * return data form stylist
     * table by using stylist id
     * @return array
     */
    public function findStylistById($id)
    {
        return User::find($id)->stylist;
    }

    /**
     * @SWG\Post(
     *     path="/api/stylist/updata_salon",
     *     summary="create salon",
     *     tags={"Salon"},
     *     description="create salon and if exist , then you can update it using this API",
     *     security={{"passport": {}}},
     *     @SWG\Parameter(
     *         name="name",
     *         in="path",
     *         description="salon's name",
     *         required=true,
     *         type="string",
     *     ),
     *     @SWG\Parameter(
     *         name="location",
     *         in="path",
     *         description="location",
     *         required=false,
     *         type="string",
     *     ),
     *      @SWG\Parameter(
     *         name="latitude",
     *         in="path",
     *         description="latitude",
     *         required=false,
     *         type="number",
     *     ),
     *     @SWG\Parameter(
     *         name="longitude",
     *         in="path",
     *         description="longitude",
     *         required=false,
     *         type="number",
     *     ),
     *      @SWG\Parameter(
     *         name="images[]",
     *         in="path",
     *         description="images max 10 pieces",
     *         required=false,
     *         type="string",
     *     ),
     *      @SWG\Parameter(
     *         name="service_name[]",
     *         in="path",
     *         description="service_name",
     *         required=false,
     *         type="string",
     *     ),
     *    @SWG\Parameter(
     *         name="item[]",
     *         in="path",
     *         description="item array has item_name and item_price",
     *         required=false,
     *         type="string",
     *     ),
     *    @SWG\Parameter(
     *         name="working_days[]",
     *         in="path",
     *         description="working_days and time object",
     *         required=false,
     *         type="string",
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="successful operation message ",
     *     ),
     *     @SWG\Response(
     *         response="422",
     *         description="validation error",
     *     ),
     *     @SWG\Response(
     *         response="500",
     *         description="error something went wrong",
     *     ),
     * )
     */
    public function updateSalon(Request $request)
    {
        $validator =  Validator::make(
            $request->all() ,[
             'name'              => 'required',
             'location'          => '',
             'images.*'          => 'mimes:jpg,jpeg,gif,png',
             'service_name'      => 'array',
             'item'              => '',
             'working_days'      => ''
         ]);

        try {
            $stylist = $this->findStylistById(Auth::id());
            $salon   = Salon::find($stylist->salon_id);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }

            if ($request->name) {
                $salon->name = $request->name;
            }

            if ($request->location) {
                foreach ($request->location as $key => $value){
                    $salon->address    = $value['address'];
                    $salon->latitude   = $value['latitude'];
                    $salon->longitude  = $value['longitude'];
                }
            }

            if ($request->hasFile('images')) {
                $this->deleteSalonImages(Auth::id());
                foreach ($request->file('images') as $file) {
                    $name = time().'.'.$file->getClientOriginalName();
                    $file->move(public_path().'/uploads/salon/'.Auth::id(), $name);
                    $data[] = $name;
                }
                $salon->images = json_encode($data);
            }

            $salon->save();

            $removeService = Services::where('salon_id', $stylist->salon_id)->delete();

            if ($request->service_name) {
                foreach ($request->service_name as $service) {
                    $sevicesModel           = new Services();
                    $sevicesModel->name     = $service;
                    $sevicesModel->salon_id = $stylist->salon_id;

                    $sevicesModel->save();
               }
            }

            $removeService = SalonMenu::where('salon_id', $stylist->salon_id)->delete();

            if ($request->item) {
                foreach ( $request->item as $value){
                    $dataForMenu = [
                        'item_name'  => $value['item_name'],
                        'item_price' => $value['item_price'] ,
                        'salon_id'   => $salon->id
                    ];
                  $menu =  SalonMenu::insert($dataForMenu);
               }
            }

            $removeService = SalonWorkTime::where('salon_id', $stylist->salon_id)->delete();
            $workModel      = new SalonWorkTime();
            $this->workingDaysCreate($workModel ,$request, $salon->id);

            return response()->json(['success' => 'successfully updated your salon',
                                       'salon' => $salon],200);
        } catch (Exception $e) {
            return response()->json(['error' => 'something went wrong!'], 500);
        }
    }

    /**
     * @SWG\Get(
     *     path="/api/stylist/show_my_salon",
     *     summary="show salon info",
     *     tags={"Salon"},
     *     description="get salon info like name,images ,address , services ,for owner 'stylist'",
     *     security={{"passport": {}}},
     *     @SWG\Response(
     *         response=200,
     *         description="salon full info and images , beauty pro and working times",
     *         @SWG\Schema(ref="#/definitions/Salon"),
     *     ),
     *     @SWG\Response(
     *         response="500",
     *         description="error something went wrong",
     *     ),
     * )
     */
    public function showMySalon()
    {
        try{
            $salonOwner                     = $this->findStylistById(Auth::id());
            $mySalon                        = Salon::find($salonOwner->salon_id);
            $images                         = json_decode($mySalon->images);
            $path                           = public_path().'/uploads/salon/'. Auth::id().'/';
            $mySalon['services']            = Salon::find($salonOwner->salon_id)->service;
            $mySalon['menu']                = Salon::find($salonOwner->salon_id)->menu;
            $workTimes                      = Salon::find($salonOwner->salon_id)->workTime;

            if ($workTimes) {
                $mySalon['workingTimes']    =
                [
                'monday'    => json_decode($workTimes->monday),
                'tuesday'   => json_decode($workTimes->tuesday),
                'wednesday' => json_decode($workTimes->wednesday),
                'thursday'  => json_decode($workTimes->thursday),
                'friday'    => json_decode($workTimes->friday),
                'saturday'  => json_decode($workTimes->saturday),
                'sunday'    => json_decode($workTimes->sunday),
                 ];
            }

            $salonEmployee                  = Salon::find($salonOwner->salon_id)->beautyPro;

            if (count($salonEmployee)){
                foreach ($salonEmployee as $pro  )  {
                    $beautyPro[] = User::find($pro->id);
                }
                $mySalon['beautyProfessionals'] = $beautyPro;
            } else {
                $mySalon['beautyProfessionals'] = [];
            }


            if ($images) {
                foreach($images as $image) {
                    $allImages[] = $path . $image;
                }
                $mySalon['images'] = $allImages;

            } else {
                $mySalon['images'] = [];
            }

            return response()->json(['salon' => $mySalon], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'something went wrong!'], 500);
        }
    }

    /**
     * @SWG\Post(
     *     path="/api/stylist/update_location",
     *     summary="update salon's location",
     *     tags={"Salon"},
     *     description="update salon location for salon owner 'stylist'",
     *     security={{"passport": {}}},
     *     @SWG\Parameter(
     *         name="location",
     *         in="path",
     *         description="location will be updated by loation object that consist ,address, latitude,longitude ",
     *         required=false,
     *         type="string",
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="location update",
     *         @SWG\Schema(ref="#/definitions/Salon"),
     *     ),
     *     @SWG\Response(
     *         response="500",
     *         description="error something went wrong",
     *     ),
     * )
     */
    public function updateLocation(Request $request)
    {
        $this->validate($request, [
                'location' => ''
            ]);

        $stylist = $this->findStylistById(Auth::id());

        if ($salon = Salon::find($stylist->salon_id)) {

            if ($request->location) {
                foreach ($request->location as $key => $value){
                    $salon->address   = $value['address'];
                    $salon->latitude  = $value['latitude'];
                    $salon->longitude = $value['longitude'];
               }
            }

            return response()->json(['message' => 'location updated successfuly'], 200);
        } else {
            return response()->json(['error' => 'something went wrong!'], 500);
        }
    }

    /**
     * @SWG\Get(
     *     path="/api/stylist/show_my_location",
     *     summary="show salon's location",
     *     tags={"Salon"},
     *     description="get salon location for salon owner 'stylist'",
     *     security={{"passport": {}}},
     *     @SWG\Response(
     *         response=200,
     *         description="return location and iside lat and long",
     *         @SWG\Schema(ref="#/definitions/Salon"),
     *     ),
     *     @SWG\Response(
     *         response="500",
     *         description="error something went wrong",
     *     ),
     * )
     */
    public function showMyLocation()
    {
        try{
            $salonOwner = $this->findStylistById(Auth::id());
            $mySalon    = Salon::find($salonOwner)->first();

            return response()->json(
                ['location' => [
                    'address'   => $mySalon->address ,
                    'latitude'  => $mySalon->latitude ,
                    'longitude' => $mySalon->longitude
                    ] ], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'something went wrong!'], 500);
        }
     }

    /**
     * @SWG\Get(
     *     path="/api/stylist/my_services",
     *     summary="show salon's services for stylis 'for self'",
     *     tags={"Salon"},
     *     description="get salon services'",
     *     security={{"passport": {}}},
     *     @SWG\Response(
     *         response=200,
     *         description="return all salon service",
     *         @SWG\Schema(ref="#/definitions/Services"),
     *     ),
     *     @SWG\Response(
     *         response="500",
     *         description="error something went wrong",
     *     ),
     * )
     */
     public function listServices()
     {
        try{
            $salonOwner  = $this->findStylistById(Auth::id());
            $allServices = Salon::findOrfail($salonOwner->salon_id)->service;

            return response()->json( ['allServices' => $allServices ], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'something went wrong!'], 500);
        }
     }

    /**
     * @SWG\Get(
     *     path="/api/stylist/delete_service/{id}",
     *     summary="delete specifc service",
     *     tags={"Salon"},
     *     description="delete sservice",
     *     security={{"passport": {}}},
     *     @SWG\Response(
     *         response=200,
     *         description="service deleted",
     *         @SWG\Schema(ref="#/definitions/Services"),
     *     ),
     *     @SWG\Response(
     *         response="500",
     *         description="error something went wrong",
     *     ),
     * )
     */
    public function deleteService($id)
    {
       try{
           $salonOwner  = $this->findStylistById(Auth::id());
           $service     = Services::where('salon_id', $salonOwner->salon_id)
                                   ->where('id',$id);

           $service->delete();

           return response()->json(['success' => 'service deleted'] , 200);
       } catch (Exception $e) {
           return response()->json(['error' => 'something went wrong!'], 500);
       }
    }

    /**
     * @SWG\Get(
     *     path="/api/stylist/show_service/{id}",
     *     summary="show specifc service",
     *     tags={"Salon"},
     *     description="show sservice",
     *     security={{"passport": {}}},
     *     @SWG\Response(
     *         response=200,
     *         description="service",
     *         @SWG\Schema(ref="#/definitions/Services"),
     *     ),
     *     @SWG\Response(
     *         response="500",
     *         description="error something went wrong",
     *     ),
     * )
     */
    public function showService($id)
    {
       try{
           $service = Services::findOrfail($id);

           return response()->json(['service' => $service] , 200);
       } catch (Exception $e) {
           return response()->json(['error' => 'something went wrong!'], 500);
       }
    }

    /**
     * @SWG\Post(
     *     path="/api/stylist/update_service/{id}",
     *     summary="add service to salon",
     *     tags={"Salon"},
     *     description="update services",
     *     security={{"passport": {}}},
     *     @SWG\Parameter(
     *         name="name",
     *         in="path",
     *         description="name",
     *         required=true,
     *         type="string",
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="service updated successfuly",
     *         @SWG\Schema(ref="#/definitions/Services"),
     *     ),
     *   @SWG\Response(
     *         response=422,
     *         description="validation error",
     *     ),
     *     @SWG\Response(
     *         response="500",
     *         description="error something went wrong",
     *     ),
     * )
     */
    public function updateService(Request $request ,$id)
    {
        $validator =  Validator::make(
            $request->all() ,[
             'name'       => 'required',
             ]);

        try {
            if ($validator->fails()) {

                return response()->json(['error' => $validator->errors()], 422);
            }

            $salonOwner    = $this->findStylistById(Auth::id());
            $service       =  Services::where('salon_id', $salonOwner->salon_id)
                                      ->where('id',$id);
            $service->name  = $request->name;

            return response()->json(['service' => $service, 'message' => 'service updateed'] , 200);
       } catch (Exception $e) {
           return response()->json(['error' => 'something went wrong!'], 500);
       }
    }

     public function deleteSalonImages($id)
     {
        try{
            $stylist = $this->findStylistById(Auth::id());
            $salon   = Salon::find($stylist->salon_id);
            $images  = json_decode($salon->images);

            foreach ($images as $key => $value) {
                $path = public_path().'/uploads/salon/'. $id .'/'. $value;
                File::delete($path);
            }

        } catch (Exception $e) {
            return response()->json(['error' => 'something went wrong!'], 500);
        }
     }

     /**
     * @SWG\Post(
     *     path="/api/salon/invite",
     *     summary="invite stylist to salon",
     *     tags={"Salon"},
     *     description="check email if exist and type shoudl be stylist then send email invitation",
     *     security={{"passport": {}}},
     *     @SWG\Parameter(
     *         name="email",
     *         in="path",
     *         description="email",
     *         required=true,
     *         type="string",
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="Invitation sent to entered email",
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="We can't find a user with that e-mail address.",
     *     ),
     * )
     */
     public function sendInvitation(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
        ]);
        $user           = User::where('email', $request->email)
                                ->where('user_type','stylist')->first();

        if (!$user) {
            return response()->json([
                'message' => 'We can\'t find a user with that e-mail address.'
                ], 404);
        }

        $stylist        = $this->findStylistById(Auth::id());
        $beautyPro      = $this->getStylistByEmail($request->email);
        $isAlreadyExist = SalonEmployee::where('salon_id',$stylist->salon_id)
                                        ->where('stylist_id', $beautyPro->stylist_id)->first();

        if ($isAlreadyExist) {
            return response()->json([
            'message' => 'Beauty Pro is aleardy exist.'
                ], 422);
        }

        $isJoinedToAnotherSalon =  SalonEmployee::find($beautyPro->stylist_id);

        if ($isJoinedToAnotherSalon) {
            return response()->json([
                'message' => 'Beauty Pro employeed at another salon'
                ], 422);
        }

        $invitation = Invitation::updateOrCreate(
            ['email' => $user->email],
             [
                'name'  => $user->username,
                'email' => $user->email,
                'token' => Str::random(60)
             ]
        );

        if ($user && $invitation) {
            $user->notify(
                new SalonInvitation($stylist->salon_id)
            );

            return response()->json([
                'message' => 'Invitation sent to '.$user->email 
                ], 200);
        }
    }

    /**
     * @SWG\Get(
     *     path="/api/find/salon/{id}",
     *     summary="find invited salon",
     *     tags={"Salon"},
     *     description="find invited salon ",
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         description="id",
     *         required=true,
     *         type="integer",
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="return json salon object",
     *         @SWG\Schema(ref="#/definitions/Salon"),
     *     ),
     *     @SWG\Response(
     *         response="500",
     *         description="something went wrong!",
     *     ),
     * )
     */
    public function findSalon($id)
    {
        try{
            $salonOwner                     = Stylist::where('salon_id', $id)->first();
            $mySalon                        = Salon::find($id);
            $images                         = json_decode($mySalon->images);
            $path                           = public_path().'/uploads/salon/'. $salonOwner->user_id .'/';
            $mySalon['services']            = Salon::find($id)->service;
            $mySalon['menu']                = Salon::find($id)->menu;
            $workTimes                      = Salon::find($id)->workTime;

            if ($workTimes) {
                $mySalon['workingTimes']    =
                [
                    'monday'    => json_decode($workTimes->monday),
                    'tuesday'   => json_decode($workTimes->tuesday),
                    'wednesday' => json_decode($workTimes->wednesday),
                    'thursday'  => json_decode($workTimes->thursday),
                    'friday'    => json_decode($workTimes->friday),
                    'saturday'  => json_decode($workTimes->saturday),
                    'sunday'    => json_decode($workTimes->sunday),
                 ];
            }

            $salonEmployee                  = Salon::find($id)->beautyPro;

            if (count($salonEmployee)){
                foreach ($salonEmployee as $pro  )  {
                    $beautyPro[] = User::find($pro->id);
                }
                $mySalon['beautyProfessionals'] = $beautyPro;
            } else {
                $mySalon['beautyProfessionals'] = 'No Beauty Pro Found';
            }


            if ($images) {
                foreach($images as $image) {
                    $allImages[] = $path . $image;
                }
                $mySalon['images'] = $allImages;

            } else {
                $mySalon['images'] = 'no images';
            }

            return response()->json(['salon' => $mySalon], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'something went wrong!'], 500);
        }
    }


    public function workingDaysCreate($workModel ,$request, $salonId)
    {
        $workModel->salon_id = $salonId;

        if ($request->monday) {
            $workModel->monday = json_encode($request->monday);
        }

        if ($request->tuesday) {
            $workModel->tuesday = json_encode($request->tuesday);
        }

        if ($request->wednesday) {
            $workModel->wednesday = json_encode($request->wednesday);
        }

        if ($request->thursday) {
            $workModel->thursday = json_encode($request->thursday);
        }

        if ($request->friday) {
            $workModel->friday = json_encode($request->friday);
        }

        if ($request->saturday) {
            $workModel->saturday = json_encode($request->saturday);
        }

        if ($request->sunday) {
            $workModel->sunday = json_encode($request->sunday);
        }

        $workModel->save();

        $times['monday']    = json_decode($workModel->monday);
        $times['tuesday']   = json_decode($workModel->tuesday);
        $times['wednesday'] = json_decode($workModel->wednesday);
        $times['thursday']  = json_decode($workModel->thursday);
        $times['friday']    = json_decode($workModel->friday);
        $times['saturday']  = json_decode($workModel->saturday);
        $times['sunday']    = json_decode($workModel->sunday);

        $this->workingTimes = $times;
    }
    /**
     * @SWG\Get(
     *     path="/api/salon/show_my_beauty_pro",
     *     summary="show my salons's beauty pro",
     *     tags={"Salon"},
     *     description="show my salons's beauty pro workers",
     *     @SWG\Response(
     *         response=200,
     *         description="return json beautyProfessionals list for your salon"
     *     ),
     *     @SWG\Response(
     *         response="500",
     *         description="something went wrong",
     *     ),
     * )
     */
    public function showMyBeautyPro()
    {
        try{
            $stylist       = $this->findStylistById(Auth::id());
            $salonEmployee = Salon::find($stylist->salon_id)->beautyPro;

            if (count($salonEmployee)){
                foreach ($salonEmployee as $pro  )  {
                    $beautyPro[] = User::find($pro->id);
                }
                $beautyProfessionals = $beautyPro;
            } else {
                $beautyProfessionals = 'No Beauty Pro Found';
            }

            return response()->json(['beautyProfessionals' => $beautyProfessionals], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'something went wrong!'], 500);
        }
    }

    /**
     * @SWG\Get(
     *     path="/api/salon/exclude/{username}",
     *     summary="ecxlude salon's beauty professional",
     *     tags={"Salon"},
     *     description="ecxlude salon's beauty professional",
     *     @SWG\Response(
     *         response=200,
     *         description="return json beautyProfessionals list for your salon",
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="user not found",
     *     ),
     *     @SWG\Response(
     *         response="500",
     *         description="something went wrong!",
     *     ),
     * )
     */
    public function deleteMyBeautyPro($username)
    {
        try{

            $stylist       = $this->getStylistByUsername($username);
            $salonEmployee = SalonEmployee::where('salon_id',$stylist->salon_id)
                                            ->where('stylist_id',$stylist->stylist_id)->delete();

            if ($salonEmployee) {

                return response()->json(['message'=>'BeautyPro Excluded'], 200);
            } else {

                return response()->json(['message'=>'user not found'], 422);
            }

        } catch (Exception $e) {
            return response()->json(['error' => 'something went wrong!'], 500);
        }
     }

    public function getStylistByUsername($username)
    {
        $stylist = DB::table('stylists')
                    ->join('users', 'stylists.user_id', '=', 'users.id')
                    ->select('stylists.id as stylist_id',
                             'stylists.salon_id',
                             'stylists.is_salon_owner',
                             'users.id as user_id',
                             'users.username',
                             'users.email',
                             'users.fullname',
                             'users.age',
                             'users.phone_number',
                             'users.whats_app',
                             'users.address',
                             'users.introduction',
                             'users.longitude',
                             'users.latitude',
                             'users.profile_photo',
                             'users.background_photo',
                             'users.user_type'
                    )
                    ->where('users.username', '=', $username)
                    ->first();

     return $stylist;
    }

    public function getStylistByEmail($email)
    {
        $stylist = DB::table('stylists')
                    ->join('users', 'stylists.user_id', '=', 'users.id')
                    ->select('stylists.id as stylist_id',
                             'stylists.salon_id',
                             'stylists.is_salon_owner',
                             'users.id as user_id',
                             'users.username',
                             'users.email',
                             'users.fullname',
                             'users.age',
                             'users.phone_number',
                             'users.whats_app',
                             'users.address',
                             'users.introduction',
                             'users.longitude',
                             'users.latitude',
                             'users.profile_photo',
                             'users.background_photo',
                             'users.user_type'
                    )
                    ->where('users.email', '=', $email)
                    ->first();

     return $stylist;
    }

    public function addMeToSalonStaff()
    {
        try{
            $stylist         = $this->findStylistById(Auth::id());
            $meAsStaffMember = SalonEmployee::where('salon_id',$stylist->salon_id)
                                            ->where('stylist_id',$stylist->stylist_id)->first();

            if ($salonEmployee) {

                return response()->json(['message'=>'You already exist'], 200);
            } else {

                // add me to salon staff
            }

        } catch (Exception $e) {
            return response()->json(['error' => 'something went wrong!'], 500);
        }
    }
}
