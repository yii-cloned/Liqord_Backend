<?php

namespace App\Http\Controllers;

use Auth;
use Input;
use Session;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Redirect;
use Hash;
use Response;
use Datatables;
use Mail;
use Excel;
use DB;
use DateTime;
use File;
use Image;

/** Models */
use App\User;
use App\Products;



class ProductController extends Controller {

    public function index($location_id = false) {
        if (!HelperController::checkPrivilege()) {
            return Redirect::to('admin');
        }
       
        $page_title = "Products";
        $breadcrumb = "<li class='active'>Products</li>";
        $active = "store_locations";
        $active_sub = "liquorsale";

        return view("admin.product")->with("page_title", $page_title)
                        ->with("breadcrumb", $breadcrumb)
                        ->with("active", $active)
                        ->with("location_id", $location_id)
                        ->with("active_sub", $active_sub);
    }

    /**
     * Users result page - ajax
     */
    public function results($location_id = false) {
      
        $storeid = Auth::user()->store_id;
        $results=Products::where('store_id',$storeid)->where('location_id',$location_id)->get();
        return Datatables::of($results)  
                 ->editColumn('created_at', function ($result) {

                               return strip_tags(date('d F Y',  strtotime($result->created_at)));
                         
                        })
                        ->addColumn('edit', function ($result) {
                            return "<a href='" . asset("admin/product/update/{$result->location_id}/{$result->id}") . "'>
                    <span class='fa fa-pencil-square fa-2x'></span>
                </a>";
                        })
                        ->addColumn('action', function ($result) {
                            if ($result->is_active == 0) {
                                $url = asset("admin/product/enable") . "/";
                                $link = "<a href='#' id='alter_link'
                               onclick=\"return action_confirm('{$url}{$result->id}','Product','Enable this Product ?');\" >
                                <i class='fa fa-check-circle fa-2x'></i>
                            </a>";
                            } else if ($result->is_active == 1) {
                                $url = asset("admin/product/disable") . "/";
                                $link = "<a href='#' id='alter_link'
                               onclick=\"return action_confirm('{$url}{$result->id}','Product','Disable this Product ?');\" >
                                <i class='fa fa-times fa-2x'></i>
                            </a>";
                            }
                            return $link;
                        })
                       
                        ->make(true);
    }
   
     /**
     * User enable page
     */
    
    
    
    public function view($location_id,$id = false) {
       
         if (!HelperController::checkPrivilege()) {
            return Redirect::to('admin');
        }
        $submit = "update";
        if ($id == false) {
            $submit = "add";
            $result =new Products;
            $page_title = "Add Product";
            $header = "<header>Add Product</header>";
        } else {
            $result = Products::where('id',$id)->first();
            if (is_null($result)) {
                Session::flash("fail", "No such Product");
                return Redirect::back();
            }
          
             $page_title = "Update Product Details";
             $header = "<header>Update Product</header>";
  
        }
        
        
        $breadcrumb = "<li class='active'>Products</li>";
        $active = "store_locations";
        $active_sub = "liquorsale";

        return view("admin.productView")->with("page_title", $page_title)
                        ->with("breadcrumb", $breadcrumb)
                        ->with("active", $active)
                        ->with("header", $header)
                        ->with("active_sub", $active_sub)
                        ->with("id", $id) 
                        ->with("location_id", $location_id) 
                        ->with("result", $result)
                        ->with("submit", $submit);
  
    }

    /**
     * Product update page - Update
     */
    
      public function update(Request $request,$location_id,$id = false) {
        if(!HelperController::checkPrivilege()) {
            return Redirect::to("admin");
        }   
        $data = $request->all();
        
        $rules = array();
        if($id == false) { 
            $Products =new Products;
            $rules = array('category' => 'required','brand_name' => 'required','proof' => 'required','size' => 'required',
                           'pack_size' => 'required','base_price' => 'required','license_price' => 'required');
        
            
        }
        else {
            $Products = Products::find($id);
            if(is_null($Products)) {
                Session::flash("fail", "No such Product");
                return Redirect::back();
            }
            
        }
        
        $valid = Validator::make($data, $rules);
       
        if ($valid->passes()) {
            $Products->store_id = Auth::user()->store_id; 
            $Products->location_id = $location_id;
            $Products->category = HelperController::clean($data['category']);
            $Products->code = HelperController::clean($data['code']);
            $Products->ada_no = HelperController::clean($data['ada_no']);
            $Products->brand_name=HelperController::clean($data['brand_name']);
            $Products->proof = HelperController::clean($data['proof']);
            $Products->size = HelperController::clean($data['size']);
            $Products->pack_size=HelperController::clean($data['pack_size']);
            $Products->base_price = HelperController::clean($data['base_price']);
            $Products->license_price = HelperController::clean($data['license_price']);
            $Products->minimum_shelf_price=HelperController::clean($data['minimum_shelf_price']);
            $Products->shelf_number = HelperController::clean($data['shelf_number']);
            $Products->bottle_position=HelperController::clean($data['bottle_position']);
            $save = $Products->save();
      
            if ($save) {
                if($id == false) {
                    Session::flash("success", "Product saved");
                    return Redirect::to("admin/product/update/{$location_id}/{$Products->id}");
                }
                else {
                    Session::flash("success", "Product updated");  
                    return Redirect::back();
                }                               
            }
            else {
                // Something went wrong.
                Request::flash();
                Session::flash("fail", "Opps... Something went wrong");
            }               

            return Redirect::back();
        } 
        else {
            // Something went wrong.
            return Redirect::back()->withErrors($valid);
        }
    } 
    public function enable($id) {
        if (!HelperController::checkPrivilege()) {
            return Redirect::to('admin');
        }

        $save = Products::where("id", $id)->update(['is_active' => 1]);
        if ($save) {
            Session::flash("success", "Product enabled");
        } else {
            // Something went wrong.
            Session::flash("fail", "Opps... Something went wrong");
        }

        return Redirect::back();
    }

    /**
     * News disable page     
     */
    public function disable($id) {
        if (!HelperController::checkPrivilege()) {
            return Redirect::to('admin');
        }

        $save = Products::where("id", $id)->update(['is_active' => 0]);
        if ($save) {
            Session::flash("success", "Product disabled");
        } else {
            // Something went wrong.
            Session::flash("fail", "Opps... Something went wrong");
        }

        return Redirect::back();
    }  
    
     public function viewImport($location_id) {
        if (!HelperController::checkPrivilege()) {
            return Redirect::to('admin');
        }

        $page_title = "Import Excel";
        $breadcrumb = "<li class='active'>Import Excel</li>";
        $active = "store_locations";
        $active_sub = "";

        return view("admin.productExport")->with("page_title", $page_title)
                        ->with("breadcrumb", $breadcrumb)
                        ->with("active", $active)
                        ->with("location_id", $location_id)
                        ->with("active_sub", $active_sub);
    }
    
     public function importProduct($location_id) {
        if (!HelperController::checkPrivilege()) {
            return Redirect::to('admin');
        }
        $store_id = Auth::user()->store_id; 
        if (Input::hasFile('import_file')) {
            $path = Input::file('import_file')->getRealPath();
            $data = Excel::load($path, function($reader) {
                        
                    })->get();
            if (!empty($data) && $data->count()) {
                $category='';
                $date = date('Y-m-d');
//                print_r($date);die;
                foreach ($data as $key => $value) {
              if(isset($value->ada)){
                    if (($value->ada == 'NO.') || ($value->ada == "") || ( strpos($value->ada, 'NEW ITEMS:') !== false )) {
                    //    echo $value->ada;echo "\n";
                    } else if (strpos($value->ada, 'DELETED ITEMS:') !== false) {
                        break;
                    } else {
                        if (($value->code == "") || ($value->brand_name == "")) {
                            $category = $value->ada;
                        } else {
                            $insert[] = ['store_id' => $store_id,'location_id' => $location_id,'category' => $category,'ada_no' => $value->ada, 'code' => $value->code, 'brand_name' => $value->brand_name, 'proof' => $value->proof, 'size' => $value->size, 'pack_size' => $value->pack, 'base_price' => $value->base, 'license_price' => $value->licensee, 'minimum_shelf_price' => $value->minimum,'created_at' => $date];
                        }
                    }
                }
             else {
                 return Redirect::back();
                   }
                }
                if (!empty($insert)) {
                    DB::table('products')->insert($insert);
                    Session::flash("success", "Insert Record successfully");
                    return Redirect::to("admin/product/{$location_id}");
                    return Redirect::back();
                }
            }
        }
        return back();
    }

    
   
}


