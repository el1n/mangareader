<?
	error_reporting(E_ALL & ~E_NOTICE);

	define("BOOKSHELF","../bookshelf");

	define("F_OPTION_THUMBNAIL",0x00010000);
	define("F_OPTION_COMPRESS",0x00020000);
	define("F_OPTION_SPLIT",0x00040000);
	define("F_OPTION_SEQUENTIAL",0x00080000);
	define("F_OPTION_REVERSE",0x00100000);
	define("F_OPTION_SLIDE",0x00200000);

	$memcached = new Memcached();
	$memcached->setOption(Memcached::OPT_BINARY_PROTOCOL,1);
	$memcached->setOption(Memcached::OPT_COMPRESSION,1);
	$memcached->setOption(Memcached::OPT_PREFIX_KEY,getenv("SCRIPT_FILENAME"));
	$memcached->addServer("127.0.0.1",11211);

	switch($_GET["op"]){
		case "i":
			$dir = BOOKSHELF.getenv("PATH_INFO");
			if(is_dir($dir)){
#				$r = array(
#					"c" =>200,
#					"value" =>usort(
#						array_map(
#							function($a){
#								return(array(
#									filename =>$a,
#									m =>filemtime($dir."/".$a)
#								));
#							},
#							array_values(preg_grep("/^(?!\\.).*?(?<!\.zip)$/",scandir($dir)))
#						),
#						function($a,$b){
#							return(strnatcasecmp($a["filename"],$b["filename"]));
#						}
#					)
#				);
				$r = array(
					"c" =>200,
					"value" =>array()
				);
				foreach(array_values(preg_grep("/^(?!\\.).*?(?<!\.zip)$/",scandir($dir))) as $a){
							preg_match_all("/(?:^(.+?) |(\[(.*?)\]))(?=(?:\[[^\[\]]*?\])*?$)/",$a,$m);

							$im = new Imagick();
							if(is_file($dir."/".$a)){
								switch(strtolower(pathinfo($a,PATHINFO_EXTENSION))){
									case "bmp":
									case "gif":
									case "jpeg":
									case "jpg":
									case "png":
										$im->pingImage($dir."/".$a);
										break;
								}
							}

							if(!$im->getNumberImages()){
								array_push($r["value"],array(
									filename =>$a,
	#								m =>filemtime($dir."/".$a)
									m =>filemtime(BOOKSHELF.getenv("PATH_INFO")."/".$a),
									w =>$m[3][1],
									i =>$m[3][2] ? $m[3][2] : $m[3][1],
									t =>$m[1][0],
									a =>implode(" / ",array_values(array_filter(array($m[3][1],$m[3][2],$m[3][3]),strlen)))
								));
							}else{
								if($im->getImageWidth() <= $im->getImageHeight())
									array_push($r["value"],array(
										filename =>$a,
		#								m =>filemtime($dir."/".$a)
										m =>filemtime(BOOKSHELF.getenv("PATH_INFO")."/".$a),
										w =>$m[3][1],
										i =>$m[3][2] ? $m[3][2] : $m[3][1],
										t =>$m[1][0],
										a =>implode(" / ",array_values(array_filter(array($m[3][1],$m[3][2],$m[3][3]),strlen))),
										s =>""
									));
								else{
									array_push($r["value"],array(
										filename =>$a,
										m =>filemtime(BOOKSHELF.getenv("PATH_INFO")."/".$a),
										w =>$m[3][1],
										i =>$m[3][2] ? $m[3][2] : $m[3][1],
										t =>$m[1][0],
										a =>implode(" / ",array_values(array_filter(array($m[3][1],$m[3][2],$m[3][3]),strlen))),
										s =>"=1&p=0"
									));
									array_push($r["value"],array(
										filename =>$a,
										m =>filemtime(BOOKSHELF.getenv("PATH_INFO")."/".$a),
										w =>$m[3][1],
										i =>$m[3][2] ? $m[3][2] : $m[3][1],
										t =>$m[1][0],
										a =>implode(" / ",array_values(array_filter(array($m[3][1],$m[3][2],$m[3][3]),strlen))),
										s =>"=1&p=1"
									));
								}
							}
				}
			}else{
				$r = array(
					"c" =>403
				);
			}
			header("Content-Type: application/json");
			echo(json_encode($r));
			exit(0);
			break;
		case "m":
			$file = BOOKSHELF.getenv("PATH_INFO");
			$file = preg_replace("/\/\$/","",$file);
#			if(is_dir($file)){
#				$file = $file."/".reset(preg_grep("/\.(gif|jpe?g|png)$/i",scandir($file)));
#			}
			while(is_dir($file)){
				#$file = $file."/".reset(preg_grep("/(^(?!\\.)|\.(gif|jpe?g|png)$)/i",scandir($file)));
				$file = $file."/".reset(preg_grep("/^(?!\\.).*?(?<!\.db)(?<!\.zip)$/i",scandir($file)));
			}
			if(!$r = $memcached->get($file)){
				$r = new Imagick();
				if(is_file($file)){
					switch(strtolower(pathinfo($file,PATHINFO_EXTENSION))){
						case "bmp":
						case "gif":
						case "jpeg":
						case "jpg":
						case "png":
							$r->readImage($file);
							break;
						case "avi":
						case "mp4":
							error_log($file);
							$bin = shell_exec("ffmpeg -i '$file' -ss 10 -vframes 1 -f image2 pipe:1");
							$r->readImageBlob($bin);
							break;
					}
				}
				if(!$r->getNumberImages()){
					$r->newImage(1,1,"#000000");
				}
				$r->thumbnailImage(100,100,1);
				$r->setImageFormat("jpeg");
				$r->setImageCompressionQuality(66);

				$memcached->set($file,$r->getImageBlob());
#				switch(pathinfo($file,PATHINFO_EXTENSION)){
#					case "jpg":
#					case "jpeg":
#						$gd = imagecreatefromjpeg($file);
#						break;
#				}
#				if(!$gd){
#					$gd = imagecreate(100,100);
#				}
#				$scale = min(100 / imagesx($gd),100 / imagesy($gd));
#				$gd2 = imagecreatetruecolor(imagesx($gd) * $scale,imagesy($gd) * $scale);
#				imagecopyresampled($gd2,$gd,0,0,0,0,imagesx($gd2),imagesy($gd2),imagesx($gd),imagesy($gd));
#
#				ob_start();
#				imagejpeg($gd2);
#				$r = ob_get_contents();
#				ob_end_clean();
#
#				$memcached->set($file,$r);
			}
			header("Content-Type: image/jpeg");
			echo($r);
			exit(0);
			break;
		case "g":
			$file = BOOKSHELF.getenv("PATH_INFO");
			if(is_file($file)){
				$r = new Imagick($file);

				if($_GET["s"])
					if($_GET["p"] == 0){
						$r->cropImage($r->getImageWidth() * 0.52,$r->getImageHeight(),0,0);
					}else if($_GET["p"] == 1){
						$r->cropImage($r->getImageWidth() * 0.52,$r->getImageHeight(),$r->getImageWidth() * 0.48,0);
					}
				if($_GET["c"]){
					$r->modulateImage(100,0,100);
					$r->thumbnailImage($_GET["w"] / 2,$_GET["h"] / 2,1);
					$r->setImageDepth(4);
					$r->setImageFormat("jpeg");
					$r->setImageCompressionQuality(75);
				}
			}else{
				$r = new Imagick();
				$r->newImage(1,1,"#000000");
			}
			header("Content-Type: image/jpeg");
			echo($r);
			exit(0);
			break;
		case "a":
			echo(json_encode(array_values(preg_grep("/^(?!\\.)/",scandir(BOOKSHELF)))));
			exit;
			break;
		case "b":
			echo(json_encode(array_values(preg_grep("/^(?!\\.)/",scandir("./books/".$_GET["comic"])))));
			exit;
			break;
		case "c":
			header("Content-type: image/jpeg");
			$dir = "./books/".$_GET["comic"]."/".($_GET["n"] ? $_GET["n"] : "01");
			$file = reset(preg_grep("/^(?!\\.)/",scandir($dir)));
			$img = new Imagick($dir."/".$file);
			$img->thumbnailImage(300,0);

			echo($img);
			break;
		case "d":
			echo(json_encode(array_values(preg_grep("/^(?!\\.)/",scandir("./books/".$_GET["comic"]."/".$_GET["n"])))));
			exit;
			break;
		default:
			break;
	}
?>
<!DOCTYPE HTML>
<html>
<head>
<title></title>
<meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
<meta name="viewport" content="user-scalable=no">
<script src="//cdnjs.cloudflare.com/ajax/libs/jquery/1.11.1/jquery.min.js" type="text/javascript"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/jquery-easing/1.3/jquery.easing.min.js" type="text/javascript"></script>
<script src="/lib/js/jquery.touchswipe/1.6.6/jquery.touchSwipe.min.js" type="text/javascript"></script>
<script src="/lib/js/coffee-script/1.8.0/coffee-script.js" type="text/javascript"></script>
<script src="/lib/js/natural-compare-lite/1.2.1/min.js" type="text/javascript"></script>
<script type="text/javascript" charset="UTF-8">
</script>
<script type="text/coffeescript">
@C_MODE_MASK = 0x0FFF
@C_MODE_UNKNOWN = 0x0000
@C_MODE_PC = 0x0001
@C_MODE_NOTEBOOK = 0x0002
@C_MODE_PHONE = 0x0004
@C_MODE_TABLET = 0x0008
@C_MODE_VERTICAL = 0x0000
@C_MODE_HORIZON = 0x0200
@F_OPTION_THUMBNAIL = 0x00010000
@F_OPTION_COMPRESS = 0x00020000
@F_OPTION_MINIMIZE = 0x00020000
@F_OPTION_SPLIT = 0x00080000
@F_OPTION_REVERSE = 0x00100000
@F_OPTION_SLIDE = 0x00200000
@F_OPTION_SEQUENTIAL = 0x00400000
@F_OPTION_FREEFINGER = 0x00800000
@F_OPTION_VERTICAL_SPLIT_AUTO = 0x01000000
@C_CACHE_AHEAD = 3
@C_CACHE_BEHIND = 1
@C_IMG_NULL = $("<canvas width=1 height=1 />")[0].toDataURL()

@nemui = new class
	ui:new class extends @
		draw:(@b) ->
			if @b?
				@preference.set(@b)

			switch @b & C_MODE_MASK
				when C_MODE_UNKNOWN
					#alert("#{$(window).width()}x#{$(window).height()}x1")
					#alert("#{$("body").width()}x#{$("body").height()}x2")
					#alert("#{screen.width}x#{screen.height}x2")
					if navigator.userAgent.match(/Android.*Mobile|iPhone/)
						if $(window).width() / $(window).height() < 1
						#if screen.width / screen.height < 1
							@draw(C_MODE_PHONE|C_MODE_VERTICAL|F_OPTION_COMPRESS)
						else
							@draw(C_MODE_PHONE|C_MODE_HORIZON)
					else if navigator.userAgent.match(/Android|iPad/)
						if $(window).width() / $(window).height() < 1
						#if screen.width / screen.height < 1
							@draw(C_MODE_TABLET|C_MODE_VERTICAL|F_OPTION_COMPRESS)
						else
							@draw(C_MODE_TABLET|C_MODE_HORIZON)
					else
						@draw(C_MODE_PC)
				when C_MODE_PC|C_MODE_VERTICAL,C_MODE_PC|C_MODE_HORIZON
					$("#MODE_PC")[0].disabled = 0
					$("#MODE_PHONE_VERTICAL")[0].disabled = 1
					$("#MODE_PHONE_HORIZON")[0].disabled = 1
					$("#MODE_TABLET_VERTICAL")[0].disabled = 1
					$("#MODE_TABLET_HORIZON")[0].disabled = 1
				when C_MODE_NOTEBOOK|C_MODE_VERTICAL,C_MODE_NOTEBOOK|C_MODE_HORIZON
					$("#MODE_PC")[0].disabled = 0
					$("#MODE_PHONE_VERTICAL")[0].disabled = 1
					$("#MODE_PHONE_HORIZON")[0].disabled = 1
					$("#MODE_TABLET_VERTICAL")[0].disabled = 1
					$("#MODE_TABLET_HORIZON")[0].disabled = 1
				when C_MODE_PHONE|C_MODE_VERTICAL
					$("#MODE_PC")[0].disabled = 1
					$("#MODE_PHONE_VERTICAL")[0].disabled = 0
					$("#MODE_PHONE_HORIZON")[0].disabled = 1
					$("#MODE_TABLET_VERTICAL")[0].disabled = 1
					$("#MODE_TABLET_HORIZON")[0].disabled = 1
				when C_MODE_PHONE|C_MODE_HORIZON
					$("#MODE_PC")[0].disabled = 1
					$("#MODE_PHONE_VERTICAL")[0].disabled = 1
					$("#MODE_PHONE_HORIZON")[0].disabled = 0
					$("#MODE_TABLET_VERTICAL")[0].disabled = 1
					$("#MODE_TABLET_HORIZON")[0].disabled = 1
				when C_MODE_TABLET|C_MODE_VERTICAL
					$("#MODE_PC")[0].disabled = 1
					$("#MODE_PHONE_VERTICAL")[0].disabled = 1
					$("#MODE_PHONE_HORIZON")[0].disabled = 1
					$("#MODE_TABLET_VERTICAL")[0].disabled = 0
					$("#MODE_TABLET_HORIZON")[0].disabled = 1
				when C_MODE_TABLET|C_MODE_HORIZON
					$("#MODE_PC")[0].disabled = 1
					$("#MODE_PHONE_VERTICAL")[0].disabled = 1
					$("#MODE_PHONE_HORIZON")[0].disabled = 1
					$("#MODE_TABLET_VERTICAL")[0].disabled = 1
					$("#MODE_TABLET_HORIZON")[0].disabled = 0
				else
					console.log("! Unknown mode #{@b}.")
			console.log("? mode=#{@b}")
	url:new class extends @
		set:(a...) ->
			url = [
				location.origin
				location.pathname
				location.search
				location.hash
			]

			for _ in a
				if _.match(/^\//)
					url[1] = _
				else if _.match(/^\?/)
					if _.length > 1
						url[2] = _
					else
						url[2] = ""
				else if _.match(/^\#/)
					if _.length > 1
						url[3] = _
					else
						url[3] = ""
				else
					console.log("! Invalid parameter #{_}")
			history.replaceState(null,null,url.join(""))
	preference:new class extends @
		constructor:(@b = (location.search.match("b=(\\d+)") || [0,F_OPTION_THUMBNAIL|F_OPTION_SEQUENTIAL])[1]) ->
			for param,v of {
#				"Display Mode":
#					"Desktop":C_MODE_PC
#					"Notebook":C_MODE_NOTEBOOK
#					"Phone":C_MODE_PHONE
#					"Tablet":C_MODE_TABLET
				"Show Thumbnails":
					"":F_OPTION_THUMBNAIL
				"Compression/Minimize":
					"":F_OPTION_COMPRESS
#				"Horizontal Split":
#					"":F_OPTION_SPLIT
				"Sequential Read":
					"":F_OPTION_SEQUENTIAL
				"Reverse Read":
					"":F_OPTION_REVERSE
				"Slide Effect":
					"":F_OPTION_SLIDE
				"sensor test":
					"":F_OPTION_FREEFINGER
			}
				mask = Object.keys(v).reduce(((a,b) -> a | v[b]),0)
				console.log(mask)
				for k,b of v
					$("#preference").append(
						$("#preference .cloak")
						.clone(1)
						.removeClass("cloak")
						.find(".label:eq(0)").html(param).end()
						.find(".label:eq(1)").html(k).end()
						.find("input")
							.prop("type",(["radio","checkbox"])[!(Object.keys(v).length - 1) + 0])
							.prop("name",mask)
							.prop("value",b)
							.prop("checked",@b & b)
							.change(=> @update(arguments...))
							.end()
						.find(".input").click(-> $(@).parents(".field").find("input").click()).end()
					)
		set:(b,mask) ->
			@b &= ~mask
			@b |= b
			for _ in $("#preference input")
				$(_).prop("checked",@b & $(_).val())
		open:() ->
			$("#preference")
			.focus()
			.show()
			.animate({"opacity":0.900},500,"easeOutCubic")
		close:() ->
			$("#preference")
			.animate({"opacity":0.000},500,"easeOutCubic",-> $(@).hide())
		toggle:() ->
			if $("#preference").css("display") == "none"
				@open()
			else
				@close()
		update:(a) ->
			if $(a.target).prop("checked")
				@b = @b & ~$(a.target).prop("name") | $(a.target).prop("value")
			else
				@b = @b & ~$(a.target).prop("name")
			@ui.draw(@b)
			@url.set("?b=#{@b}")
	navi:new class extends @
		constructor:() ->
			@change("none")
		change:(a) ->
			$("#navi")
			.removeClass("none")
			.removeClass("slide")
			.removeClass("img")
			.removeClass("vid")
			.addClass(a)
	
			$("#navi div:NOT(.#{a})")
			.hide()
			$("#navi div.#{a}:NOT(.cloak)")
			.prop("opacity",0)
			.show()
			.animate({"opacity":1.000},500,"easeOutCubic")
	menu:new class extends @
		draw:(key = "filename",order) ->
			$("#menu .book").remove()
		
			$.getJSON("<?=getenv("SCRIPT_NAME")?>/#{@geturl({op:"i"})}",(a) =>
				for _ in a.value.sort((a,b) -> String.naturalCompare(a[key],b[key]))
					((a) =>
						$("#menu").append(
							$("#menu .cloak")
							.clone(1)
							.prop("class","book")
							.find(".thumbnail").css("background-image","url(\"<?=getenv("SCRIPT_NAME")?>/#{@geturl(a.filename,{op:"m"})}\")").end()
							.find(".title span").html(a.t).end()
							.find(".author span").html(a.a).end()
							.find(".mtime span").html("#{(new Date(a.m * 1000)).getMonth() + 1}/#{(new Date(a.m * 1000)).getDate()}").end()
							.click(() => @volume.open(a.filename))
						)
					)(_)
			)
		grep:() ->
			$("#menu .book").each(->
				cnv = (a) ->
					a = a.toUpperCase()
					a = a.replace(/[\u3041-\u3096]/g,(a) -> String.fromCharCode(a.charCodeAt(0) + 0x60))
		
				if cnv($(@).find("span").html()).indexOf(cnv($(".grep input").val())) != -1
					$(@).show()
				else
					$(@).hide()
			)
		sort:(key = "filename",order) ->
			$("#menu .book").sort((a,b) -> String.naturalCompare($(a).find(".title span").html(),$(b).find(".title span").html())).each(->
				$(@).appendTo("#menu")
			)
	volume:new class extends @
		open:(com) ->
			$(".volume").remove()
		
			$.getJSON("<?=getenv("SCRIPT_NAME")?>/#{@geturl(com,{op:"i"})}",(a) =>
				for _ in a.value
					((vol) =>
						$("#book").append(
							$("#book .cloak")
							.clone(1)
							.prop("class","volume")
							.find(".thumbnail").css("background-image","url(\"<?=getenv("SCRIPT_NAME")?>/#{@geturl(com,vol,{op:"m"})}\")").end()
							.find("span:eq(0)").html(vol).end()
							.click(() => @reader.open(com,vol))
						)
					)(_.filename)
			)
		
			if (@preference.b & C_MODE_MASK) == (C_MODE_PHONE|C_MODE_VERTICAL)
				$("#main").animate({"left":-$("#menu").width()},500,"easeOutCubic")
				@navi.change("slide")
		
			@url.set("/#{com}/")
	reader:new class extends @
		open:(@com,@vol,i = 0) ->
			$.getJSON("<?=getenv("SCRIPT_NAME")?>/#{@geturl(@com,@vol,{op:"i"})}",(a) =>
				@url.set("/#{@com}/#{@vol}/")

				@list = a.value.sort((a,b) -> String.naturalCompare(a.filename,b.filename))

				@preference.close()
				$("#frame")
				.css("left","")
				.css("top","")
				.css("width","")
				.css("height","")
				.show(0,-> $(@).focus())
				.animate({"opacity":1.000},500,"easeOutCubic")

				$("#frame,#navi")
				.addClass("img")
				.removeClass("vid")
				@navi.change("img")

				@jump(i);
			)
			if @preference.b & F_OPTION_FREEFINGER
				window.addEventListener("devicemotion",@sensor)
		jump:(i) ->
			if 0 <= i < @list.length * (!!(@preference.b & F_OPTION_SPLIT) + 1)
				$("#frame img:NOT(.cloak)#{(":NOT(##{_})" for _ in [(i - C_CACHE_BEHIND)..(i + C_CACHE_AHEAD)]).join("")}").remove()

				switch !!(@preference.b & F_OPTION_SLIDE) * (i - @index)
					when 1
						@make(i - 1)
						.stop()
						.css("left","0%")
						.animate({left:"100%"},250,"easeOutCubic",-> $(@).hide())
						@make(i + 0)
						.stop()
						.css("left","-100%")
						.show()
						.animate({left:"0"},250,"easeOutCubic")
						@make(i + 1).stop().hide()
						@make(i + 2).stop().hide()
						@make(i + 3).stop().hide()
					when -1
						@make(i - 1).stop().hide()
						@make(i + 0)
						.stop()
						.css("left","100%")
						.show()
						.animate({left:"0"},250,"easeOutCubic")
						@make(i + 1)
						.stop()
						.css("left","0%")
						.animate({left:"-100%"},250,"easeOutCubic",-> $(@).hide())
						@make(i + 2).stop().hide()
						@make(i + 3).stop().hide()
					else
						@make(i - 1).hide()
						@make(i + 0).show()
						@make(i + 1).hide()
						@make(i + 2).hide()
						@make(i + 3).hide()
				@index = i

				@url.set("##{@index}")
			else if $("#frame").queue().length != 0
				console.log("dup jump")
			else if i >= @list.length
				@close()
				if @preference.b & F_OPTION_SEQUENTIAL
					if $(".volume:contains(#{@vol}) + .volume").size()
						$("#frame")
						.queue(=>
							@reader.open(@reader.com,$(".volume:contains(#{nemui.reader.vol}) + .volume").find("span").html(),0)
							$("#frame").dequeue();
						)
			else
				@close()
		make:(i) ->
			if 0 <= i < @list.length * (!!(@preference.b & F_OPTION_SPLIT) + 1)
				if $("##{i}").size()
					console.log("? Image #{i} cached.")
				else
					$("#frame").append(
						$("<img>")
						.prop("src",C_IMG_NULL)
						.prop("id",i)
						.css("background-image","url(\"<?=getenv("SCRIPT_NAME")?>/#{
							@geturl(
								@com
								@vol
								if @preference.b & F_OPTION_REVERSE
									@list[@list.length - 1 - parseInt(i / (!!(@preference.b & F_OPTION_SPLIT) + 1))].filename
								else
									@list[parseInt(i / (!!(@preference.b & F_OPTION_SPLIT) + 1))].filename
								{
									"op":"g"
#									"s":@preference.b & F_OPTION_SPLIT
#									"p":i % 2
									"s":@list[parseInt(i / (!!(@preference.b & F_OPTION_SPLIT) + 1))].s
									"w":document.body.clientWidth
									"h":document.body.clientHeight
									"c":@preference.b & F_OPTION_COMPRESS
								}
							)}\")")
					)
					console.log("? Image #{i} maked.")
				return $("##{i}")
			else
				return $("##{i}")
		close:(i) ->
			if (@ui.b & C_MODE_MASK) == (C_MODE_PHONE|C_MODE_VERTICAL)
				@navi.change("slide")
			else
				@navi.change("none")

			if @index == 0 || @index == @list.length * (!!(@preference.b & F_OPTION_SPLIT) + 1) - 1
				$("#frame")
				#.animate({"opacity":0},500,"easeOutCubic",-> $(@).hide())
				.animate({"opacity":0},500,"easeOutCubic")
				.hide(0,-> $("#frame *").remove())
		
			else
				$("#frame")
				#.animate({"opacity":0},500,"easeOutCubic",-> $(@).hide())
				.animate({"left":"100%","top":"100%","width":0,"height":0,"opacity":0},500,"easeOutCubic")
				.hide(0,-> $("#frame *").remove())
		
				$("#conf").append(
					$("#conf .cloak")
					.clone(1)
					.removeClass("cloak")
					.find("img")
						.css("background-image",
							$("#frame img:visible").css("background-image").replace(/op=./,"op=m")
						)
						.prop("alt",[@com,@vol,@index].join("/"))
						.click((a) =>
							m = $(a.target).prop("alt").split("/")
							@volume.open(m[0])
							@reader.open(m[0],m[1],parseInt(m[2]))
							$(a.target).parent().remove()
						)
						.end()
				)
				console.log($("#frame img:visible").css("background-image"));
			@url.set("/#{@com}/","#")
			if @preference.b & F_OPTION_FREEFINGER
				window.removeEventListener("devicemotion",@sensor)
		sensor:(a) =>
			$("title").text(@dir)
			if a.acceleration.x > 3 && @dir != 2
				if @dir == -1
					nemui.reader.jump(nemui.reader.index - 1)
					@dir = 2
				else
					@dir = 1
			else if a.acceleration.x < -2 && @dir != 2
				if @dir == 1
					nemui.reader.jump(nemui.reader.index + 1)
					@dir = 2
				else
					@dir = -1
			else if -1 <= a.acceleration.x <= 1
				@dir = 0
	geturl:(a...,b) ->
		return("#{a.map(encodeURIComponent).join("/")}?#{("#{k}=#{v}" for k,v of b).join("&")}")

$(window).load(() ->
	$(@).on("orientationchange",() ->
		$("body").hide().css("opacity",0);
		nemui.ui.draw(C_MODE_UNKNOWN)
		$("body")
		.show()
		.animate({"opacity":1.000},500,"easeOutCubic")
	)

	$("#header").html(location.hostname)
	$("img.thumbnail").each(() ->
		@src = C_IMG_NULL
	)

	id = 0;

	$("#frame")
	.prop("tabindex",0)
#	.bind("click",-> nemui.reader.jump(nemui.reader.index + 1))
#	.bind("contextmenu",-> !!(nemui.reader.jump(nemui.reader.index - 1) && false))
	.bind("contextmenu",-> false)
#	.bind("mousedown",() -> $(@).trigger("touchstart"))
#	.bind("mouseup",() -> $(@).trigger("touchend"))
#	.bind("touchstart",(a) ->
#		x = a.clientX
#		y = a.clientY
#
#		id = setTimeout("nemui.reader.close(1)",500)
#	)
#	.bind("touchend",(a) ->
#		clearTimeout(id)
#		###
#		$("#frame video").each(() ->
#			if @paused
#				@.play()
#				alert("play")
#			else
#				@.pause()
#				alert("play")
#		)
#		###
#	)
#	.bind("touchmove",(a) ->
#		clearTimeout(id)
#	)
	.bind("mousewheel",(a) ->
		if a.originalEvent.wheelDelta < 0
			nemui.reader.jump(nemui.reader.index + 1)
		else
			nemui.reader.jump(nemui.reader.index - 1)
	)
	.bind("DOMMouseScroll",(a) ->
		if a.originalEvent.detail > 0
			nemui.reader.jump(nemui.reader.index + 1)
		else
			nemui.reader.jump(nemui.reader.index - 1)
	)
	.keydown((a) ->
		switch a.keyCode
			when 0x25,0x22,0x6b,0x41,0x57,0x20
				nemui.reader.jump(nemui.reader.index + 1)
			when 0x27,0x21,0x6d,0x44,0x53
				nemui.reader.jump(nemui.reader.index - 1)
			when 0x23
				nemui.reader.jump(nemui.reader.list.length * (!!(nemui.preference.b & F_OPTION_SPLIT) + 1) - 1)
			when 0x24
				nemui.reader.jump(0)
			when 0x1b
				nemui.reader.close(1)
			else
				console.log("keyCode 0x#{a.keyCode.toString(16)} (#{a.keyCode})")
	)
	.swipe(
		click:(ev) ->
			console.log("click")
			switch ev.button
				when 0
					nemui.reader.jump(nemui.reader.index + 1)
				when 2
					nemui.reader.jump(nemui.reader.index - 1)
				when undefined
					nemui.reader.jump(nemui.reader.index + 1)
				when 1
					0
				else
					alert("! Unknown button #{ev.button}.")
		swipeRight:() -> nemui.reader.jump(nemui.reader.index + 1)
		swipeLeft:() -> nemui.reader.jump(nemui.reader.index - 1)
		hold:() ->
			nemui.reader.close()
			$(@).trigger("MSPointerCancel")
			$(@).trigger("pointercancel")
			$(@).trigger("touchcancel")
		doubleTapThreshold:100
	)
#	.touchwipe(
#		wipeLeft:() -> nemui.reader.jump(nemui.reader.index - 1)
#		wipeRight:() -> nemui.reader.jump(nemui.reader.index + 1)
#	)

	$("#preference")
	#.prop("tabnemui.reader.index",1)
	.blur(() -> closepreference())

	nemui.ui.draw()
	nemui.menu.draw()

	m = "<?=getenv("PATH_INFO")?>".replace(/^\//,"").split("/")
	if m[0]?.length > 0
		nemui.volume.open(m[0])
	if m[1]?.length > 0
		nemui.reader.open(m[0],m[1],parseInt(location.hash.replace(/^#/,"")))
)

@back = () ->
	$("#main").animate({"left":0},500,"easeOutCubic")
	$("#navi .a").animate({"opacity":0.000},500,"easeOutCubic",-> $(@).hide())

@read = (c = manga,n,i = 0) ->
	manga = c
	volume = n

	if volume.match("\.mp4$")
		$.ajaxSetup({async:false})
		$.getJSON("<?=getenv("SCRIPT_NAME")?>/#{encodeURIComponent(c)}/#{n}/?op=i",(a) ->
		)
		$.ajaxSetup({async:true})
		window.list = undefined
		open(1)
		player()
		history.replaceState(null,null,"#{location.protocol}//#{location.hostname}/#{c}/#{n}/#{location.search}")
	else
		$.getJSON("<?=getenv("SCRIPT_NAME")?>/#{encodeURIComponent(c)}/#{n}/?op=i",(a) ->
			window.list = a.value.sort((a,b) -> String.naturalCompare(a.filename,b.filename))
			open()
			jump(i);
		)
		history.replaceState(null,null,"#{location.protocol}//#{location.hostname}/#{c}/#{n}/#{location.search}##{i}")

@open = (a) ->
	nemui.preference.close()
	$("#frame")
	.css("left","")
	.css("top","")
	.css("width","")
	.css("height","")
	.show(0,-> $(@).focus())
	.animate({"opacity":1.000},500,"easeOutCubic")

	if a
		$("#frame,#navi")
		.addClass("vid")
		.removeClass("img")
		navi.change("vid")
	else
		$("#frame,#navi")
		.addClass("img")
		.removeClass("vid")
		navi.change("img")

@player = () ->
	$("#frame").append(
		$("<video preload=\"auto\" controls data-setup=\"{}\">")
		.prop("id",0)
		.prop("class","video-js vjs-default-skin")
		.prop("src","/direct/#{encodeURIComponent(manga)}/#{encodeURIComponent(volume)}")
		.attr("width","100%")
		.attr("height","100%")
		.show()
		.css("display","")
	)
	videojs($(".video-js")[0],{},->
	)
	###
	a = $("video")[0];
	a.load()
	#a.play()
	a.webkitEnterFullscreen?()
	a.requestFullScreen?()
	a.mozRequestFullScreen?()
	a.webkitRequestFullScreen?()
	setTimeout("$(\"video\")[0].load();alert(1)",1000);
	###
</script>
<style id="common" type="text/css">
.valign {
	position: relative;
	top: calc(50% - 0.5em);
	top: -webkit-calc(50% - 0.5em);
}

html, body {
	width: 100%;
	height: 100%;
	margin: 0px;
	padding: 0px;
	overflow: hidden;
	color: #ff7788;
}

#navi,#conf {
	width: 100%;
	height: 0%;
	background-color: #382f2f;
	z-index: 1;
}
#navi.img {
	background-color: #382f2f;
}
#navi.vid {
	background-color: #1f1f1f;
}

#navi .a, #conf .a {
	float: left;
	height: 100%;
}

#navi .b, #conf .b {
	text-align: center;
	width: 100%;
	height: 100%;
}

#navi .c, #conf .c {
	float: right;
	height: 100%;
}

#navi > *, #conf > * {
	margin-left: 1em;
	margin-right: 1em;
}

#navi span:hover {
	cursor: pointer;
}

#navi span::selection {
	background-color: transparent;
}

#conf .thumbnail {
	box-sizing: border-box;
	padding: 10%;
	height: 100%;
}

#navi span {
	font-weight: bold;
	background-clip: padding-box;
	padding: 0.5em;
	border-radius: 0.5em;
}

#navi.none span,#navi.slide span,#nabi.img span{
	background-color: #ffffff;
	border: 1px #000000 solid;
}
#navi.vid span{
	color: #c0c0c0;
	background-color: #606060;
	border: 1px #000000 solid;
}


#main {
	width: 0%;
	height: 0%;
	position: relative;
}

#menu, #book {
	float: left;
	width: 0%;
	height: 100%;
	overflow-y: scroll;
	-webkit-overflow-scrolling: touch;
	z-index: 0;
}

#menu::-webkit-scrollbar,#book::-webkit-scrollbar{
   -webkit-appearance: none;
}

#menu::-webkit-scrollbar-thumb,#book::-webkit-scrollbar-thumb {
    background-color: #606060;
    border: 1px solid transparent;
    border-radius: 9px;
    background-clip: content-box;
}

#menu::-webkit-scrollbar-track,#book::-webkit-scrollbar-track {
    background-color: #fff4f8;
    width: 1%;
}

.grep, .sort {
	text-align: center;
	margin: 0.5em;
	background-color: #fff4f8;
	border: 1px #ffccd4 solid;
}

.grep label {
	width: 100%;
	padding: 0.5em;
	box-sizing: border-box;
	margin-right: 0;
}

.grep input {
	width: 80%;
	font-size: inherit;
}

.sort {
	height: 2.5em;
}

.sort span {
	border-radius: 0.5em;
	padding: 0.3em;
	background-color: #ffffff;
	border: 1px #ffccd4 solid;
}

.sort div {
	float: left;
	width: 25%;
	height: 100%;
}

.sort span:hover {
	cursor: pointer;
}

.book {
	margin: 0.5em;
	border: 1px #ffccd4 solid;
}

.book .thumbnail {
	float:left;
	margin-right: 0.5em;
	//background-color: #fff4f8;
}

.book > * {
	height: 100%;
}

.book .title {
	width: 100%;
	height: 66%;
	background-color: #fff4f8;
	font-size: 100%;
}

.book .author{
//	width: 100%;
	height: 33%;
	color: #888888;
	font-size: 80%;
	float: left;
}

.book .mtime{
	height: 33%;
	color: #aaaaaa;
	font-size: 60%;
	float: right;
}

.book .title span, .book .author span {
}
.book .desc {
	//padding-left: 0em;
}


.volume .label {
	background-color: #fff4f8;
	border: 1px #ffccd4 solid;
}

.thumbnail, #frame img:not(.cloak) {
	display: block;
	background-clip: content-box;
	background-size: contain;
	background-repeat: no-repeat;
	background-position: 50%;
}

.cloak {
	display: none;
}

.volume {
	float: left;
}
.volume > * {
	width: 100%;
}

.volume div {
	text-align: center;
}

#frame {
	position: absolute;
	display: none;
	width: 100%;
	//height: 100%;
	background-color: #1f1f1f;
	opacity: 0;
	z-index: 3;
	user-select: none;
	user-select: none;
	-webkit-user-select: none;
}

#frame:focus {
	outline: 0;
}

#frame img,#frame video {
	width: 100%;
	height: 100%;
	position: absolute;
//	position: relative;
	-webkit-touch-callout:none;
//	user-select: none;
//	-webkit-user-select: none;
}

#frame.img {
	height: 100%;
}
#image,#frame .thumbnail {
	width: 100%;
	height: 100%;
}

#image video {
	width: 100%;
	height: 100%;
}

#image img {
	display: none;
}

#preference {
	display: none;
	position: absolute;
	right: 0;
	width: 0;
	height: 0;
	background-color: #302020;
	opacity: 0;
	padding-left: 1em;
	z-index: 1;
}

#preference .field {
	border-bottom: 1px #888888 solid;
	width: 100%;
	height: 0;
}

#preference img {
	vertical-align: middle;
}

#preference .label {
	width: 40%;
	min-width: 0%;
	//max-width: 90%;
	word-wrap: normal;
	height: 100%;
	float: left;
}
#preference .value {
	width: 15%;
	height: 100%;
	float: left;
}

#preference input {
	top: -4px;
	height: 100%;
	vertical-align: middle;
	position: relative;
}

#preference .input {
	width: 100%;
	height: 100%;
	left: 0;
	top: 0;
	position: relative;
	opacity: 0%;
}

</style>
<style id="MODE_PC" type="text/css">
body {
	font-size: 10pt;
}

#navi,#conf {
	//position: absolute;
	height: 48px;
}

#conf {
	bottom: 0;
}

#frame.vid {
	top: 48px;
	height: calc(100% - 48px);
	height: -webkit-calc(100% - 48px);
}


#main {
	//margin-top: 64px;
	width: 100%;
	height: calc(100% - 96px);
}

#menu {
	width: 320px;
}

#book {
	width: calc(100% - 320px);
}

.book {
	height: 48px;
}

.volume {
	margin: 2px;
	width: 64px;
}

#preference input {
	width: 1.5em;
}

#preference  {
	width: 400px;
	height: 100%;
}

#preference .field {
	height: 40px;
}
</style>
<style id="MODE_PHONE_VERTICAL" type="text/css">
body {
	font-size: 24pt;
}

#navi,#conf {
	height: 10%;
}

#main {
	width: 200%;
	height: 80%;
}

#frame.vid {
	top: 10%;
	height: calc(100% - 10%);
	height: -webkit-calc(100% - 10%);
}


#menu, #book {
	width: 50%;
}

.book {
	height: 20%;
}

.volume {
	margin: 1%;
	width: 23%;
}

#preference  {
	width: 80%;
	height: 80%;
}

#preference .field {
	height: 12.5%;
}

#preference input {
	width: 6em;
}
</style>
<style id="MODE_PHONE_HORIZON" type="text/css">
body {
	font-size: 16pt;
}

#navi,#conf {
	height: 12.5%;
}

#main {
	width: 100%;
	height: 75%;
}

#frame.vid {
	top: 12.5%;
	height: calc(100% - 12.5%);
	height: -webkit-calc(100% - 12.5%);
}


#menu {
	width: 50%;
}

#book {
	width: 50%;
}

.book {
	height: 25%;
}

.volume {
	margin: 1%;
	width: 31%;
}

#preference  {
	width: 50%;
	height: 75%;
}

#preference .field {
	height: 15%;
}

#preference input {
	width: 4em;
}
</style>
<style id="MODE_TABLET_VERTICAL" type="text/css">
body {
	font-size: 12pt;
}

#navi,#conf {
	height: 5%;
}

#main {
	width: 100%;
	height: 90%;
}

#menu {
	width: 40%;
}
#book {
	width: 60%;
}

#frame.vid {
	top: 5%;
	height: calc(100% - 5%);
	height: -webkit-calc(100% - 5%);
}


.book {
	height: 10%;
}

.volume {
	margin: 1%;
	width: 18%;
}
#preference input {
	width: 3em;
}

#preference  {
	width: 40%;
	height: 90%;
}

#preference .field {
	height: 7.5%;
}
</style>
<style id="MODE_TABLET_HORIZON" type="text/css">
body {
	font-size: 10pt;
}

#navi,#conf {
	height: 7.5%;
}

#main {
	width: 100%;
	height: 85%;
}

#menu {
	width: 30%;
}
#book {
	width: 70%;
}

#frame.vid {
	top: 5%;
	height: calc(100% - 5%);
	height: -webkit-calc(100% - 5%);
}


.book {
	height: 12.5%;
}

.volume {
	margin: 1%;
	width: 14%;
}
#preference input {
	width: 2.5em;
}

#preference  {
	width: 40%;
	height: 92.5%;
}

#preference .field {
	height: 8%;
}
</style>
</head>
<body>
<div id="frame">
</div>
<div id="navi" class="none">
	<div class="a vid">
		<span class="valign" onclick="hogehoge()">&nbsp;×&nbsp;</span>
	</div>
	<div class="a slide img">
		<span class="valign" onclick="back()">&lt;&nbsp;Back</span>
	</div>
	<div class="c none slide img">
		<span class="valign" onclick="nemui.preference.toggle()">Preference</span>
	</div>
	<!--
	<div class="b">
		<span class="valign"></span>
	</div>
	-->
</div>
<div id="preference">
	<div class="field cloak">
		<div class="label valign">
		</div>
		<div class="label valign">
		</div>
		<div class="value">
			<label>
				<input type="checkbox">
			</label>
		</div>
		<div class="input">
		</div>
	</div>
</div>
<div id="main">
	<div id="menu">
		<div class="grep">
			<label>
				Grep:
				<input type="text" onChange="nemui.menu.grep()" onKeyUp="nemui.menu.grep()">
			</label>
		</div>
		<div class="sort">
			<div>
				<span class="valign" onClick="nemui.menu.draw('filename')">N</span>
			</div>
			<div>
				<span class="valign" onClick="nemui.menu.draw('a')">A</span>
			</div>
			<div>
				<span class="valign" onClick="nemui.menu.draw('m')">M</span>
			</div>
			<div>
				<span class="valign" onClick="nemui.menu.draw('m')">D</span>
			</div>
		</div>
		<div class="cloak">
			<img class="thumbnail">
			<div class="desc">
				<div class="title">
					<span class="valign"></span>
				</div>
				<div class="author">
					<span class="valign"></span>
				</div>
				<div class="mtime">
					<span class="valign"></span>
				</div>
			</div>
		</div>
	</div>
	<div id="book">
		<div class="cloak">
			<img class="thumbnail">
			<div class="label">
				<span class="valign"></span>
			</div>
		</div>
	</div>
</div>
<div id="conf">
	<div class="c cloak">
		<img class="thumbnail">
	</div>
</div>
</body>
</html>
