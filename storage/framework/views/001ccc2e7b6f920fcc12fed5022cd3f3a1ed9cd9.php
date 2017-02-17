<?php $__env->startSection('content'); ?>
    <div class="container">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel panel-default">
                    <div class="panel-heading">Channel Setting</div>

                    <div class="panel-body">
                        <div class="form-group">
                            <label for="name">Name</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo e(old('name')?old('name'):$channel->name); ?>">
                            <?php if($errors->has('name')): ?>
                                <div class="help-block">
                                    <?php echo e($errors->first('name')); ?>

                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="slug">Slug</label>
                            <div class="input-group">
                                <div class="input-group-addon"><?php echo e(config('app.url')); ?></div>
                                <input type="text" class="form-control" id="slug" name="slug" value="<?php echo e(old('slug')?old('slug'):$channel->slug); ?>">
                            </div>
                            <?php if($errors->has('slug')): ?>
                                <div class="help-block">
                                    <?php echo e($errors->first('slug')); ?>

                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_except(get_defined_vars(), array('__data', '__path')))->render(); ?>