<header>
	<div class="top_nav_wrapper">
		<nav>
			<ul class="left">
				<li class="home"><?php echo HTML::anchor('', __('Home')); ?></li>
				<?php if ($logged_in) { ?>
				<?php if (Auth::instance()->allowed('cl4admin') || Auth::instance()->allowed('cl4admin/model_create')) { ?>
				<li class="dbadmin has_subnav"><?php echo HTML::anchor(Route::get('cl4admin')->uri(), __('DB Admin') . '<span class="more"></span>'); ?>
					<ul class="sub_nav">
						<?php if (Auth::instance()->allowed('cl4admin')) { ?>
						<li class="cl4admin"><?php echo HTML::anchor(Route::get('cl4admin')->uri(), __('DB Admin')); ?></li>
						<?php } ?>
						<?php if (CL4::is_dev() && Auth::instance()->allowed('cl4admin/model_create')) { ?>
						<li class="model_create"><?php echo HTML::anchor(Route::get('model_create')->uri(), __('Model Create')); ?></li>
						<?php } ?>
					</ul>
				</li>
				<?php } ?>
			</ul>
			<ul class="right">
				<li class="right user has_subnav"><a href="" title="My Account"><?php echo HTML::chars($user->first_name . ' ' . $user->last_name); ?><span class="more"></span></a>
					<ul class="sub_nav right">
						<?php if (Auth::instance()->allowed('account/profile')) { ?>
						<li class="account"><?php echo HTML::anchor(Route::get('account')->uri(array('action' => 'profile')), HTML::chars(__('My Account'))); ?></li>
						<?php } ?>
						<li class="logout"><?php echo HTML::anchor(Route::get('login')->uri(array('action' => 'logout')), HTML::chars(__('Logout'))); ?></li>
					</ul>
				</li>
				<?php } else { ?>
			</ul>
			<ul class="right">
				<li class="login"><?php echo HTML::anchor(Route::get('login')->uri(), HTML::chars(__('Login'))); ?></li>
				<?php } ?>
			</ul>
		</nav>
	</div>

	<div class="page_top">
		<div class="page_top_logo"><a href="<?php echo URL::base(); ?>"><?php echo HTML::chars(SHORT_NAME . ' v' . APP_VERSION); if (isset($pageTitle) && trim($pageTitle) != '') echo ' - ' . HTML::chars($pageTitle); ?></a></div>
		<div class="clear"></div>
	</div>
	<div class="clear"></div>
</header>