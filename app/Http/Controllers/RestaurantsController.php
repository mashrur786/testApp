<?php

namespace App\Http\Controllers;

use App\Requirement;
use Illuminate\Http\Request;
use App\Restaurant;
use Session;
use Symfony\Component\VarDumper\Dumper\DataDumperInterface;
use Illuminate\Support\Facades\Log;
use Postcode;
use Auth;



class RestaurantsController extends Controller
{
    public function welcome(){

        return view('restaurants.welcome');

    }

    public function search(Request $request, Restaurant $restaurant){

       $cuisines = $restaurant->select('cuisine')->groupBy('cuisine')->get();
        $res_type = $restaurant->select('type')->groupBy('type')->get();

        //set Regex for uk postcode
        $regex = '/^(?:gir(?: *0aa)?|[a-pr-uwyz](?:[a-hk-y]?[0-9]+|[0-9][a-hjkstuw]|[a-hk-y][0-9][abehmnprv-y])(?: *[0-9][abd-hjlnp-uw-z]{2})?)$/';

        //get user location & restaurant type
        $location =  $request->location;
        $type = $request->res_type;

        //check if the location given is a postcode
        if(preg_match($regex, strtolower($location))){

           //the the postcode and get nearest postcode within within 3 miles radius.
            $data = Postcode::wardsByOutcode($location);

            //get outcodes of all the nearest postcodes
            $postcodes = [];
            foreach($data->result as $postcode){
                 $postcodes[] = $postcode->outcode;
            }

            //
            $restaurants = $restaurant->whereIn('outcode', $postcodes)
                                        ->Where('type', '=' , $type)
                                        ->get();


        } else{

            $restaurants = $restaurant->Where('area', '=', $location)
                                        ->Where('type', '=' , $type)
                                        ->get();


        };



        return view('restaurants.index',[ 'data' => $restaurants, 'cuisines' =>  $cuisines, 'types' => $res_type ]);
       // dd($restaurants_info);

    }

    /* returns */
    public function sortById(Request $request, Restaurant $restaurant){

        $id = $request->id;

        $result = $restaurant->where("id", $id)->firstOrFail();

        return $result;


    }

    /* Return a sorted result made via ajax */
    public function sort(Request $request, Restaurant $restaurant){

        $filters = $request->filters;

        if(empty($filters)){

               $results = $restaurant->all();

               return $results;
        }

        $cuisines = [];
        $types = [];

        foreach($filters as $filter){

            if($filter["filterName"] == "cuisine"){

                $cuisines[] = $filter["filterValue"];

            } else {

                $types[] = $filter["filterValue"];

            }

        }

       if(empty($types)){

             $results = $restaurant->whereIn("cuisine", $cuisines)
                                    ->orderBy("cuisine", "asc")
                                    ->get();

             return $results;

        } elseif (empty($cuisines)) {

             $results = $restaurant->whereIn("type", $types)
                                    ->orderBy("cuisine", "asc")
                                   ->get();

             return $results;

        } else {

            $results = $restaurant->whereIn("cuisine", $cuisines)
                                  ->whereIn("type", $types)
                                  ->orderBy("cuisine", "asc")
                                  ->get();

            return $results;

        }

        //$results = DB::select($query);

        /*
       $results = $restaurant->whereIn("cuisine", $cuisines)
                               ->whereIn("type", $types)
                                ->orderBy("business_name", "asc")
                                ->toSql();*/

    }


    /* Auto-complete search at welcome screen */
    public function autocompleteSearch(Request $request, Restaurant $restaurant){

        //$resName =   $request->resName;
        $resName =    $request->term;
        Log::info('restaurant_name_ajax: '. $resName);

        $data = array();

        $results = $restaurant->where('business_name', 'LIKE', '%'.  $resName  . '%')->take(5)->get();

        foreach ($results as $result) {

            $data[] = [ 'id' => $result->id, 'value' => $result->business_name ];
        }

        return response()->json($data);


    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Restaurant $restaurant)
    {

        $restaurants =  $restaurant->all();

        $cuisines = $restaurant->select('cuisine')->groupBy('cuisine')->get();
        $types = $restaurant->select('type')->groupBy('type')->get();

        return view('restaurants.index',[ 'data' => $restaurants, 'cuisines' =>  $cuisines,  'types' => $types ]);


    }
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //add a new restaurant
        $requirements = Requirement::all();

        return view('restaurants.add')->withRequirements($requirements);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $restaurant = new Restaurant;
        //store a new restaurant
        //Restaurant::create($request->all());
        $restaurant->email = $request->email;
        $restaurant->business_name = $request->business_name;
        $restaurant->type = $request->type;
        $restaurant->cuisine = $request->cuisine;
        $restaurant->description = $request->description;
        $restaurant->business_phone1 = $request->business_phone1;
        $restaurant->business_phone2 = $request->business_phone2;
        $restaurant->address = $request->address;
        $restaurant->street = $request->street;
        $restaurant->area = $request->area;
        $restaurant->town = $request->town;
        $restaurant->county = $request->county;
        $restaurant->outcode = $request->outcode;
        $restaurant->incode = $request->incode;
        $restaurant->website = $request->website;
        $restaurant->contact_name = $request->contact_name;
        $restaurant->contact_phone = $request->contact_phone;

        $restaurant->save();

        $requirements = ($request->requirements ?: []);
        $restaurant->requirements()->sync($requirements, false);

        return redirect('/restaurants');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
        return view('restaurants.show', ['restaurant' => Restaurant::findOrFail($id)]);


    }

    /**
     * Subscribe a authenticated user to specified restaurant.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function subscribe($id){
        //
        $user = Auth::user();

        $restaurant = Restaurant::find($id);

        try {
            $restaurant->users()->attach($user->id);
        } catch (\Illuminate\Database\QueryException $e) {
            Session::flash('error', 'You are already subscribed');
            return redirect('/home');
        }

         Session::flash('success', 'You are now subscribed');

         return redirect('/home');


    }

      /**
     * unSubscribe a authenticated user for specified restaurant.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function unsubscribe($id){
        //
        $user = Auth::user();

        $restaurant = Restaurant::find($id);
        $restaurant->users()->detach($user->id);


         Session::flash('success', 'You are now unsubscribed');

         return redirect('/home');


    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //

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
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //

    }
}
