
<div id="wrapper">
	<div id="main_content">
	<?php
	if ( ! empty($message)) {
		echo $message;
	}
	echo $body_html;
	?>
	</div>
	<div class="clear"></div>

<?php echo View::factory('cl4/base/footer')
	->set($kohana_view_data); ?>
</div>
<div class="clear"></div>
