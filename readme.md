### 0.准备工作
npm install
art make:auth 
配置邮箱

### 1.创建channel
composer dump-autoload 
art make:model Http/Models/Channel -m 
```
public function up()
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image_fielname')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
```
art migrate 
```
// User.php
    public function channel(){
        return $this->hasMany(Channel::class);
    }
    
// Channel.php
class Channel extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'image_filename',
    ];
    
    public function user(){
        return $this->belongsTo(User::class);
    }
}

// RegisterController
class RegisterController extends Controller
{
    use RegistersUsers;

    protected $redirectTo = '/home';

    public function __construct()
    {
        $this->middleware('guest');
    }

    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => 'required|max:255',
            'channel_name'=>'required|max:255|unique:channels,name',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|min:6|confirmed',
        ]);
    }

    protected function create(array $data)
    {
        $user =  User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
        ]);

        $user->channel()->create([
            'name'=>$data['channel_name'],
            'slug'=>uniqid(true),
        ]);
        return $user;
    }
}
```

### 2.queues
art queue:table
art queue:failed-table
art migrate 

```
// .env
QUEUE_DRIVER=database 

```

### 7.Navigation 
- 1.将navigation提取出来，放到layouts.partials._navigation中
- 2.新建serviceprovider实现变量全局可用 art make:provider ComposerServiceProvider 
```
// App/Provider/ComposerServiceProvider 
class ComposerServiceProvider extends ServiceProvider
{
    public function boot()
    {
        view()->composer('layouts.partials._navigation',NavigationComposer::class);
    }
    public function register()
    {
    }
}

// 在app.php中注册
        App\Providers\ComposerServiceProvider::class,
```
- 3.新建ViewComposer
```
// app/Http/ViewComposer/NavigationComposer.php
class NavigationComposer{
    public function compose(View $view){
        if(!Auth::check()){
            return;
        }
        $view->with('channel',Auth::user()->channel->first());
    }
}
```
- 4.在_navigation中消费该变量即可。

### 8.channel settings 
art make:controller ChannelSettingsController --resource 

```
在view中消费了$channel这个变量，从route中获取到参数，再通过controller传递给view

// web.php
Route::group(['middleware'=>['auth']],function(){
   Route::get('/channel/{channel}/edit','ChannelSettingsController@edit');
   Route::put('/channel/{channel}/edit','ChannelSettingsController@update');
});

// Channel.php
    public function getRouteKeyName()
    {
        return 'slug';
    }

// ChannelSettingsController
    public function edit(Channel $channel)
    {
        return view('channel.edit',compact('channel'));
    }
```

- 修改.env参数配置
```
// .env 
在其中将APP_URL参数修改为http://localhost:8000/
在view中消费
```

- art make:request ChannelUpdateRequest 
```
class ChannelUpdateRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $channelId = Auth::user()->channel()->first()->id;
        return [
            'name'=>'required|max:255|unique:channels,name,'.$channelId,
            'slug'=>'required|max:255|alpha_num|unique:channels,slug,'.$channelId,
            'description'=>'max:1000',
        ];
    }
}
```

- art make:policy ChannelPolicy 
```
// ChannelPolicy 
class ChannelPolicy
{
    use HandlesAuthorization;

   public function update(User $user,Channel $channel){
       return ($user->id) === ($channel->user_id);;
   }

   public function edit(User $user,Channel $channel){
       return ($user->id) === ($channel->user_id);;
   }
}

// AuthServiceProvider.php
class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        'App\Http\Models\Channel'=>'App\Policies\ChannelPolicy',
    ];
    
    public function boot()
    {
        $this->registerPolicies();
    }
}

// ChannelSettingsController.php
    public function edit(Channel $channel)
    {
        $this->authorize('edit',$channel);
        return view('channel.edit',compact('channel'));
    }
    public function update(ChannelUpdateRequest $request,Channel $channel)
    {
            $this->authorize('update',$channel);
            $channel->update([
                'name'=>$request->name,
                'slug'=>$request->slug,
                'description'=>$request->description,
            ]);
            return redirect()->to("/channel/{$channel->slug}/edit");
    }
```

#### 9.s3
忽略

#### 10.Image和Video

art make:job UploadImage 
```
class UploadImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $channel;
    public $fileId;

    public function __construct(Channel $channel,$fileId)
    {
        $this->channel = $channel;
        $this->fileId = $fileId;
    }

    public function handle()
    {
        $path = storage_path() . '/uploads/' . $this->fileId;
        $fileName = $this->fileId . '.png';
//        File::delete($path);
        $this->channel->image_filename = $fileName;
        $this->channel->save();
    }
}
```

art queue:work  #查看进行中的job
art queue:flush #删除失败的job

#### 11.Resize Image 
alter table `channels` column `image_fielname` `image_filename` vharchar(255) null;ALTER TABLE `channels` CHANGE COLUMN `image_fielname` `image_filename` varchar(255) null;

composer require intervention/image 
Intervention\Image\ImageServiceProvider::class,
'Image'=> Intervention\Image\Facades\Image::class,

#### 12.mix(elixr)
npm install 
npm run watch 

#### 13.Video Selection 
art make:controller VideoUploadController
Route::get('/upload','VideoUploadController@index')
VideoUpload组件
```
// VideoUpload.vue 
<template>
    <div class="container">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel panel-default">
                    <div class="panel-heading">Upload</div>

                    <div class="panel-body">
                        <input type="file" name="video" id="video" @change="fileInputChange" v-if="!uploading">

                        <div id="video-form" v-if="uploading &&!failed">
                            Form
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    export default {
        data(){
            return{
                uploading:false,
                uploadingComplete:false,
                failed:false,
            }
        },
        methods:{
            fileInputChange(){
                this.uploading = true;
                this.failed = false;


                console.log("File Change");
            }
        },
        mounted() {
            console.log('Component mounted.')
        }
    }
</script>

// uploade.blade.php 
@extends('layouts.app')

@section('content')
    <video-upload></video-upload>
@endsection
```

#### 14.Video Model and Migration 
art make:model Http/Models/Video -m
```
// Channel.php
    public function video(){
        return $this->hasMany(Video::class);
    }
    
// Video.php
class Video extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'title',
        'description',
        'uid',
        'video_filename',
        'video_id',
        'processed',
        'visibility',
        'allow_votes',
        'allow_comments',
        'processed_percentage',
    ];
    public function channel(){
        return $this->belongsTo(Channel::class);
    }

    public function getRouteKeyName()
    {
        return 'uid';
    }
}

// create_videos_table.php
class CreateVideosTable extends Migration
{
    public function up()
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('channel_id')->unsigned();
            $table->string('uid');
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('processed')->default(false);
            $table->string('video_id')->nullable();
            $table->string('video_filename')->nullable();
            $table->enum('visibility',['public','unlisted','private']);
            $table->boolean('allow_votes')->default(false);
            $table->boolean('allow_comments')->default(false);
            $table->integer('processed_percentage')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('cascade');
        });
    }
    public function down()
    {
        Schema::dropIfExists('videos');
    }
}
```

#### 15.Video Creation 
npm install vue-resource --save 
```
// app.js 
var VueResource = require('vue-resource');
Vue.use(VueResource)
Vue.http.headers.common['X-CSRF-TOKEN'] = $('meta[name="csrf-token"]').attr('content');  //处理csrf问题
```

art make:controller VideoController 
Route::post('/video','VideoController@store');
```
// VideoUpload.vue 
<template>
    <div class="container">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel panel-default">
                    <div class="panel-heading">Upload</div>
                    <div class="panel-body">
                        <input type="file" name="video" id="video" @change="fileInputChange" v-if="!uploading">
                        <div id="video-form" v-if="uploading &&!failed">
                            Form
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    export default {
        data(){
            return {
                uid:'',
                uploading: false,
                uploadingComplete: false,
                failed: false,
                title: 'Untitled',
                description: null,
                visibility: 'private',
            }
        },
        methods: {
            fileInputChange(){
                this.uploading = true;
                this.failed = false;
                this.file = document.getElementById('video').files[0];

                this.store().then(() => {

                });
                console.log("File Change");
            },
            store(){
                return this.$http
                    .post('/video', {
                        title: this.title,
                        description: this.description,
                        visibility: this.visibility,
                        extension: this.file.name.split('.').pop()
                    })
                    .then((response) => {
                        console.log(response.data.data.uid);
                        this.uid = response.data.data.uid;
                    });
            }
        },
        mounted() {
            console.log('Component mounted.')
        },
        created(){
        }
    }
</script>


// VideoController.php
class VideoController extends Controller
{
    public function store(Request $request){
        // generate uid
        // grab user channel
        // store the data
        $uid = uniqid(true);
        $channel = $request->user()->channel()->first();
        $video = $channel->videos()->create([
            'uid'=>$uid,
            'title'=>$request->title,
            'description'=>$request->description,
            'visibility'=>$request->visibility,
            'video_filename'=>"{$uid}.{$request->extension}",
        ]);
        return response()->json([
            'data'=>[
                'uid'=>$uid,
            ]
        ]);
    }
}
```
#### 16.Saving Video Details 
```
// VideoUpload.vue 
                        <div id="video-form" v-if="uploading &&!failed">
                            <div class="form-group">
                                <label for="title">Title</label>
                                <input type="text" class="form-control" v-model="title">
                            </div>

                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea type="text" class="form-control" v-model="description"></textarea>
                            </div>

                            <div class="form-group">
                                <label for="visibility">Visibility</label>
                                <select class="form-control" v-model="visibility">
                                    <option value="private">Private</option>
                                    <option value="unlisted">Unlisted</option>
                                    <option value="public">Public</option>
                                </select>
                            </div>
                            <span class="help-block pull-right">Save Status</span>
                            <button class="btn-default btn" type="submit" @click.prevent="update">Save</button>
                        </div>
```

art make:policy VideoPolicy 
art make:request VideoUploadRequest 
```
class VideoPolicy
{
    use HandlesAuthorization;

    public function update(User $user,Video $video){
        return $user->id === $video->channel->user_id;
    }
}

class VideoUpdateRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }
    public function rules()
    {
        return [
            'title'=>'required|max:255',
            'description'=>'max:2000',
            'visibility'=>'required',
        ];
    }
}

// VideoController.php
    public function update(VideoUpdateRequest $request,Video $video){
        $this->authorize('update',$video);

        $video->update([
            'title'=>$request->title,
            'description'=>$request->description,
            'visibility'=>$request->visibility,
            'allow_votes'=>$request->has('allow_votes'),
            'allow_comments'=>$request->has('allow_comments'),
        ]);
        if($request->ajax()){
            return response()->json(null,200);
        }
        return redirect()->back();
    }
```

#### 17.Video Upload 
修改php.ini中关于post的最大值
find / -name php.ini
sudo vim /etc/php5/cli/php.ini 
post_max_size = 100M 
upload_max_size = 100M 

```
// VideoUpload.vue 

        methods: {
            fileInputChange(){
                this.uploading = true;
                this.failed = false;
                this.file = document.getElementById('video').files[0];

                this.store().then(() => {
                    var form = new FormData();
                    form.append('video', this.file);
                    form.append('uid', this.uid);
                    this.$http.post('/upload', form, {
                        progress: (e) => {
                            if (e.lengthComputable) {
                                console.log(e.loaded + ' ' + e.total);
                                this.updateProgress(e);
                            }
                        }
                    }).then(() => {
                        this.uploadingComplete = true;
                    }, () => {
                        this.failed = true;
                    })
                }, () => {
                    this.failed = true;
                });
                console.log("File Change");
            },
            store(){
                return this.$http
                    .post('/video', {
                        title: this.title,
                        description: this.description,
                        visibility: this.visibility,
                        extension: this.file.name.split('.').pop()
                    })
                    .then((response) => {
                        console.log(response.data.data.uid);
                        this.uid = response.data.data.uid;
                    });
            },
            update(){
                this.saveStatus = 'Saving changes';
                return this.$http
                    .put('/videos/' + this.uid, {
                        title: this.title,
                        description: this.description,
                        visibility: this.visibility
                    })
                    .then((response) => {
                        console.log(response.data.data);
                        this.saveStatus = 'Changes saved';
                        setTimeout(() => {
                            this.saveStatus = '';
                        }, 2000);
                    }, () => {
                        this.saveStatus = 'Failed save';
                    });
            },
            updateProgress(e){
                e.percent = (e.loaded / e.total) + 100;
                this.fileProgress = e.percent;
            }
        },

// VideoUploadController.php
    public function store(Request $request){
        $channel = $request->user()->channel()->first();
        $video = $channel->videos()->where('uid',$request->uid)->firstOrFail();
        $request->file('video')->move(storage_path() . '/uploads',$video->video_filename);
    }
```

#### 18.







