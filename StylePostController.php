<?php

namespace App\Http\Controllers\Stylist;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\User;
use App\Report;
use App\Salon;
use App\Stylist;
use App\StylePost;
use App\PostLike;
use App\PostComment;
use Validator;
use Exception;
use Session;
use DB;
use Auth;
use File;
use Arr;

class StylePostController extends Controller
{
    /**
     * @SWG\Post(
     *     path="/api/stylist/make_style_post",
     *     summary="create style post",
     *     tags={"StylePost"},
     *     description="create new style post",
     *     security={{"passport": {}}},
     *     @SWG\Parameter(
     *         name="desc",
     *         in="path",
     *         description="post's description",
     *         required=false,
     *         type="string",
     *     ),
     *     @SWG\Parameter(
     *         name="tag",
     *         in="path",
     *         description="tags",
     *         required=false,
     *         type="string",
     *     ),
     *      @SWG\Parameter(
     *         name="images[]",
     *         in="path",
     *         description="images max 10 pieces",
     *         required=true,
     *         type="string",
     *     ),
     *     @SWG\Parameter(
     *         name="brand name",
     *         in="path",
     *         description="brand name",
     *         required=false,
     *         type="string",
     *     ),
     *     @SWG\Parameter(
     *         name="style_name",
     *         in="path",
     *         description="style name",
     *         required=false,
     *         type="string",
     *     ),
     *     @SWG\Parameter(
     *         name="color",
     *         in="path",
     *         description="color",
     *         required=false,
     *         type="string",
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="successful operation message ",
     *         @SWG\Schema(ref="#/definitions/StylePost")
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
    public function createStylePost(Request $request)
    {
       $validator =  Validator::make(
           $request->all() ,[
            'images'     => 'required',
            'images.*'   => 'mimes:jpg,jpeg,gif,png',
            'desc'       => 'max:2000',
            'tag'        => '',
            'brand_name' => '',
            'style_name' => '',
            'color'      => '',
        ]);

        try {
            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }

            $stylePost= new StylePost();

            if ($request->desc){
                $stylePost->description = $request->desc;
            }

            if ($request->tag) {
                foreach ($request->tag as $tags) {
                    $stylePost->tags =  $tags;
                }
            }

            if ($request->brand_name) {
                $stylePost->brand_name = $request->brand_name;
            }

            if ($request->style_name) {
                $stylePost->style_name = $request->style_name;
            }

            if ($request->color) {
                $stylePost->color = $request->color;
            }

            if ($request->hasFile('images')) {
                $data             = $this->uploadPostImage($request->images ,Auth::id());
                $stylePost->media = json_encode($data); 
            }

            // this might be needed for future 
            // if ($request->hasFile('clip')) {
            //     $data             = $this->uploadClip($request->clip ,Auth::id());
            //     $stylePost->media = json_encode($data);  
            // }

            $stylist = User::find(Auth::id())->stylist;
            $stylePost->stylist_id = $stylist->id;
            $stylePost->save();

            return response()->json(
                ['success' => $stylePost ,
                'message' => 'post created successfully'] ,
                200);
        } catch (Exception $e) {
            return response()->json(['error' => 'something went wrong!'], 500);
        }
    }

    private function uploadPostImage($image , $id)
    {
        foreach ($image as $file) {
            $name   = time().'.'.$file->getClientOriginalName();
            $file->move(public_path().'/uploads/style_post/'. $id ,$name);
            $data[] = $name; 
        }
        return $data;
    }

    // this might be needed for future 
    // private function uploadClip($clip ,$id)
    // {
    //     $name = time().'.'.$clip->getClientOriginalName();
    //     $clip->move(public_path().'/uploads/style_post/'. $id ,$name);
    //     return $name;
    // }

    /**
     * @SWG\Get(
     *     path="/api/stylist/delete_post/{id}",
     *     summary="delete some post by stylist",
     *     tags={"StylePost"},
     *     description="delete style post'",
     *     security={{"passport": {}}},
     *     @SWG\Response(
     *         response=200,
     *         description="Post has been deleted'",
     *      
     *     ),
     *     @SWG\Response(
     *         response="500",
     *         description="error something went wrong",
     *     ),
     * )
     */
    public function deletePost($id)
    {
        try {
            $stylePost = StylePost::findOrfail($id);
            $images    = json_decode($stylePost->media);

            if ($stylePost->stylits_id = Auth::id()) {
                $this->deletePostImages($images , Auth::id());
                $stylePost->delete();
            }

                return response()->json(['Post has been deleted'], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'something went wrong!'], 500);
        }
    }

    /**
     * @SWG\Get(
     *     path="/api/stylist/show_my_posts",
     *     summary="show all post for stylist",
     *     tags={"StylePost"},
     *     description="delete some style post'",
     *     security={{"passport": {}}},
     *     @SWG\Response(
     *         response=200,
     *         description="show all post for stylist",
     *         @SWG\Schema(ref="#/definitions/StylePost"),
     *     ),
     *     @SWG\Response(
     *         response="500",
     *         description="error something went wrong",
     *     ),
     * 
     * )
     */
    public function showAllPosts()
    {
        try {
            $stylist     = Stylist::where('user_id',Auth::id())->first();
            $auther      = User::find($stylist->user_id)
                            ->makeHidden(
                            ['email',
                            'email_verified_at',
                            'age',
                            'created_at',
                            'updated_at',
                            'introduction']
                            );
            $showAllPost = Stylist::find($stylist->id)->stylePost;

            foreach ($showAllPost as $post ){
                $path = public_path().'/uploads/style_post/'. $stylist->user_id .'/';

                foreach(json_decode($post->media) as $image) {
                    $postmedia[] = $path . $image;
                }

                $post['media'] = $postmedia;
                $data[]        = [
                                    'post'   => $post,
                                    'auther' => $auther
                                ];
            }

            return response()->json(['showAllPost'  => $data] , 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'something went wrong!'], 500);
        }
    }

    /**
     * @SWG\Get(
     *     path="/api/stylist/show_post/{id}'",
     *     summary="show specific post for stylist",
     *     tags={"StylePost"},
     *     description="show some style post'",
     *     security={{"passport": {}}},
     *     @SWG\Response(
     *         response=200,
     *         description="show specific post object for stylist ",
     *         @SWG\Schema(ref="#/definitions/StylePost"),
     *     ),
     *     @SWG\Response(
     *         response="500",
     *         description="error something went wrong",
     *     ),
     * )
     */
    public function showPost($id)
    {
        try {
            $stylePost = StylePost::findOrfail($id);
            $stylePost->Increment('views');
    
            $stylist               = Stylist::findOrfail($stylePost->stylist_id);
            $stylePost['author']   = User::findOrfail($stylist->user_id)
                                         ->makeHidden(
                                            ['email',
                                            'email_verified_at',
                                            'age',
                                            'created_at',
                                            'updated_at',
                                            'introduction']
                                            );
            $stylePost['likes']    = count(PostLike::where('style_post_id' ,$id)->get());
            $stylePost['comments'] = DB::table('post_comments')
                                        ->join('users','post_comments.user_id','=','users.id')
                                        ->select('username','comment','post_comments.created_at'
                                        ,'profile_photo','post_comments.updated_at','post_comments.style_post_id')
                                        ->where('post_comments.style_post_id', '=' , $id)
                                        ->latest()
                                        ->get();
            $stylePost['views']    = $stylePost->views ;

            $path   = public_path().'/uploads/style_post/'. $stylist->user_id .'/';

            foreach(json_decode($stylePost->media) as $image) {
                $postmedia[] = $path . $image;
            }

            $stylePost['media']    = $postmedia;

            if ($stylePost){
                return response()->json(['post' => $stylePost] , 200);
            }

        } catch (Exception $e) {
            return response()->json(['error' => 'something went wrong!'], 500);
        }
    }

    /**
     * @SWG\Post(
     *     path="/api/stylist/update_post/{id}'",
     *     summary="update style post",
     *     tags={"StylePost"},
     *     description="update style post",
     *     security={{"passport": {}}},
     *     @SWG\Parameter(
     *         name="desc",
     *         in="path",
     *         description="post's description",
     *         required=false,
     *         type="string",
     *     ),
     *     @SWG\Parameter(
     *         name="tag",
     *         in="path",
     *         description="tags",
     *         required=false,
     *         type="string",
     *     ),
     *      @SWG\Parameter(
     *         name="images[]",
     *         in="path",
     *         description="images max 10 pieces",
     *         required=true,
     *         type="string",
     *     ),
     *     @SWG\Parameter(
     *         name="brand name",
     *         in="path",
     *         description="brand name",
     *         required=false,
     *         type="string",
     *     ),
     *     @SWG\Parameter(
     *         name="style_name",
     *         in="path",
     *         description="style name",
     *         required=false,
     *         type="string",
     *     ),
     *     @SWG\Parameter(
     *         name="color",
     *         in="path",
     *         description="color",
     *         required=false,
     *         type="string",
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="successful operation",
     *         @SWG\Schema(ref="#/definitions/StylePost")
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
    public function updateStylePost(Request $request , $id)
    {
        $validator =  Validator::make(
            $request->all() ,[
            'images'     => 'required',
            'images.*'   => 'mimes:jpg,jpeg,gif,png',
            'desc'       => 'max:2000',
            'tag'        => '',
            'brand_name' => '',
            'style_name' => '',
            'color'      => '',
         ]);

        try{
            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()],401);
            }

            $stylePost = StylePost::findOrfail($id);
            
            if ($request->desc) {
                $stylePost->description = $request->desc;
            }
    
            if ($request->tag) {
                foreach ($request->tag as $tags){
                    $stylePost->tags =  $tags;
                }
            }
    
            if ($request->brand_name) {
                $stylePost->brand_name = $request->brand_name;
            }
    
            if ($request->style_name) {
                $stylePost->style_name = $request->style_name;
            }
    
            if ($request->color) {
                $stylePost->color = $request->color;
            }

            $images = json_decode($stylePost->media);
            $this->deletePostImages($images, Auth::id());

            if ($request->hasFile('images')) {
                $data             = $this->uploadPostImage(
                                    $request->images ,Auth::id());
                $stylePost->media = json_encode($data); 
            }

            $stylePost->save();    

            return response()->json(
                ['success' => $stylePost ,
                'message' => 'postt updated successfully'] ,
                200);

         }catch (Exception $e) {
             return response()->json(['error' => 'something went wrong!'], 500);
        }
    }


    private function deletePostImages($images ,$userId)
    {
       try{
           foreach ($images as $key => $value) {
               $path = public_path().'/uploads/style_post/'. $userId.'/'. $value;
               File::delete($path);
           }

       } catch (Exception $e) {
           return response()->json(['error' => 'something went wrong!'], 500);
       }
    }

     /**
     * @SWG\Post(
     *     path="/api/stylist/put_like/{post_id}",
     *     summary="like style post",
     *     tags={"StylePost"},
     *     description="like style post",
     *     security={{"passport": {}}},
     *     @SWG\Parameter(
     *         name="post_id",
     *         in="path",
     *         description="post_id",
     *         required=true,
     *         type="integer",
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="You just liked that, if liked then you unlike",
     *         @SWG\Schema(ref="#/definitions/StylePost")
     *     ),
     *     @SWG\Response(
     *         response="500",
     *         description="error something went wrong",
     *     ),
     * )
     */
    public function putLikeToPost($post_id)
    {
        try {
            $post = StylePost::findOrfail($post_id);
            $like = PostLike::where('style_post_id',$post->id)
                                        ->where('user_id', Auth::id())->first();
            if ($like) {
                $this->unlikePost($post_id);

                return response()->json(['message' => 'Unliked']);
            } else {
                $putLike                = new PostLike();
                $putLike->user_id       = Auth::id();
                $putLike->style_post_id = $post->id;

                $putLike->save();

                return response()->json([
                    'message'   => 'You just liked that',
                    'like info' => $putLike] , 200);
            }

        } catch (Exception $e) {
            return response()->json(['error' => 'something went wrong!'], 500);
        }    
    }

  
    public function unlikePost($post_id)
    {
        try {
            $post = StylePost::findOrfail($post_id);
            $like = PostLike::where('style_post_id', $post->id)
                            ->where('user_id', Auth::id())->delete();
        } catch (Exception $e) {
            return response()->json(['error' => 'something went wrong!'], 500);
        }    
    }

    /**
     * @SWG\Post(
     *     path="/api/stylist/report_post/{post_id}",
     *     summary="dislike style post",
     *     tags={"StylePost"},
     *     description="dislike style post",
     *     security={{"passport": {}}},
     *     @SWG\Parameter(
     *         name="report",
     *         in="path",
     *         description="report content",
     *         required=true,
     *         type="string",
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="Thanks, We will check your report as soon as possible",
     *         @SWG\Schema(ref="#/definitions/StylePost")
     *     ),
     *     @SWG\Response(
     *         response="500",
     *         description="error something went wrong",
     *     ),
     * )
     */
    public function reportPost(Request $request , $post_id)
    { 
        $validator =  Validator::make(
        $request->all() ,[
        'report'     => 'required',
        ]);

        try {
            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()],401);
            }

            $post                       = StylePost::findOrfail($post_id);
            $reportModel                = new Report();
            $reportModel->user_id       = Auth::id();
            $reportModel->style_post_id = $post->id;
            $reportModel->report        = $request->report;

            $reportModel->save();

            return response()->json([
                'message' => 'Thanks, We will check your report as soon as possible'] , 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'something went wrong!'], 500);
        }    
    }

     /**
     * @SWG\Post(
     *     path="/api/stylist/create_comment/{post_id}",
     *     summary="create style post comment",
     *     tags={"StylePost"},
     *     description="create style post comment",
     *     security={{"passport": {}}},
     *     @SWG\Parameter(
     *         name="comment",
     *         in="path",
     *         description="your comment ",
     *         required=true,
     *         type="string",
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="Your comment was submitted",
     *         @SWG\Schema(ref="#/definitions/StylePost")
     *     ),
     *     @SWG\Response(
     *         response="500",
     *         description="error something went wrong",
     *     ),
     * )
     */
    public function createComment(Request $request , $post_id)
    {
        $validator =  Validator::make(
            $request->all() ,[
            'comment'     => 'required',
            ]);

        try {
            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()],401);
            }
            $post    = StylePost::findOrfail($post_id);

            $postComment                = new PostComment();
            $postComment->user_id       = Auth::id();
            $postComment->style_post_id = $post->id;
            $postComment->comment       = $request->comment;

            $postComment->save();

            return response()->json([
                'message'   => 'Your comment was submitted',
                'comment info' => $postComment] , 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'something went wrong!'], 500);
        }    
    }

     /**
     * @SWG\Post(
     *     path="/api/stylist/update_comment/{id}",
     *     summary="like style post",
     *     tags={"StylePost"},
     *     description="like style post",
     *    @SWG\Parameter(
     *         name="comment",
     *         in="path",
     *         description="your comment",
     *         required=true,
     *         type="string",
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="Your comment was updated",
     *         @SWG\Schema(ref="#/definitions/StylePost")
     *     ),
     *     @SWG\Response(
     *         response="500",
     *         description="error something went wrong",
     *     ),
     * )
     */
    public function updateComment(Request $request , $id)
    {
        $validator =  Validator::make(
            $request->all() ,[
            'comment'     => 'required',
            ]);

        try {
            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()],401);
            }
            $comment                = PostComment::findOrfail($id);
            $comment->user_id       = Auth::id();
            $comment->comment       = $request->comment;

            $comment->save();

            return response()->json([
                'message'   => 'Your comment was updated',
                'comment info' => $comment] , 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'something went wrong!'], 500);
        }    
    }

    /**
     * @SWG\Get(
     *     path="/api/stylist/delete_comment/{id}'",
     *     summary="show specific post for stylist",
     *     tags={"StylePost"},
     *     description="delete comment by id",
     *     security={{"passport": {}}},
     *     @SWG\Response(
     *         response=200,
     *         description="Your comment was deleted",
     *         @SWG\Schema(ref="#/definitions/StylePost"),
     *     ),
     *     @SWG\Response(
     *         response="500",
     *         description="error something went wrong",
     *     ),
     * )
     */
    public function deleteComment($id)
    {
       try{
           $comment = PostComment::findOrfail($id);
           $comment->delete();

           return response()->json([
                'message'   => 'Your comment was deleted'] , 200);
       } catch (Exception $e) {
           return response()->json(['error' => 'something went wrong!'], 500);
       }
    }
}
