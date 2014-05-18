<?
	define(BOOKSHELF,"../bookshelf");

	$memcached = new Memcached();
	$memcached->setOption(Memcached::OPT_BINARY_PROTOCOL,1);
	$memcached->setOption(Memcached::OPT_COMPRESSION,1);
	$memcached->setOption(Memcached::OPT_PREFIX_KEY,getenv("SCRIPT_FILENAME"));
	$memcached->addServer("127.0.0.1",11211);

	switch($_GET["op"]){
		case "i":
			$dir = BOOKSHELF.getenv("PATH_INFO");
			if(is_dir($dir)){
				$r = array(
					"c" =>200,
					"value" =>array_values(preg_grep("/^(?!\\.).*?(?<!\.zip)$/",scandir($dir)))
				);
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
#			if(is_dir($file)){
#				$file = $file."/".reset(preg_grep("/\.(gif|jpe?g|png)$/i",scandir($file)));
#			}
			while(is_dir($file)){
				#$file = $file."/".reset(preg_grep("/(^(?!\\.)|\.(gif|jpe?g|png)$)/i",scandir($file)));
				$file = $file."/".reset(preg_grep("/^(?!\\.).*?(?<!\.db)(?<!\.zip)$/i",scandir($file)));
			}
			if(!$r = $memcached->get($file)){
				$r = new Imagick();
				if(!is_file($file) || !$r->readImage($file)){
					$r->newImage(1,1,"#000000");
				}
				$r->thumbnailImage(100,100,1);
				$r->setImageFormat("jpeg");
				$r->setImageCompressionQuality(80);

				$memcached->set($file,$r->getImageBlob());
				error_log($memcached->getResultCode());
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
						$r->cropImage($r->getImageWidth() / 2,$r->getImageHeight(),$r->getImageWidth() / 2,0);
					}else if($_GET["p"] == 1){
						$r->cropImage($r->getImageWidth() / 2,$r->getImageHeight(),0,0);
					}
				if($_GET["c"])
					$r->thumbnailImage($_GET["w"] / 2,$_GET["h"] / 2,1);
					$r->setImageFormat("jpeg");
					$r->setImageCompressionQuality(80);
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
<me.authorhttp-equiv="Content-Type" content="text/html;charset=UTF-8">
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js" type="text/javascript"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/jquery-easing/1.3/jquery.easing.min.js" type="text/javascript"></script>
<script src="http://www.netcu.de/templates/netcu/js/jquery.touchwipe.min.js" type="text/javascript"></script>
<script src="/jquery.touchy.min.js" type="text/javascript"></script>
<script src="http://coffeescript.org/extras/coffee-script.js" type="text/javascript"></script>
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
@C_CACHE_AHEAD = 3
@C_CACHE_BEHIND = 1
@F_OPTION_THUMBNAIL = 0x00010000
@F_OPTION_COMPRESS = 0x00020000
@F_OPTION_SPLIT = 0x00040000

b = 0
manga = ""
volume = ""
index = 0

@draw = (a = C_MODE_UNKNOWN) ->
	@mode = a

	switch mode
		when C_MODE_UNKNOWN
			if navigator.userAgent.match(/Android.*Mobile|iPhone/)
				if $(window).width() < $(window).height()
					draw(C_MODE_PHONE|C_MODE_VERTICAL)
				else
					draw(C_MODE_PHONE|C_MODE_HORIZON)
			else if navigator.userAgent.match(/Android|iPad/)
				if $(window).width() < $(window).height()
					draw(C_MODE_TABLET|C_MODE_VERTICAL)
				else
					draw(C_MODE_TABLET|C_MODE_HORIZON)
			else
				draw(C_MODE_PC)

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
			console.log("! Unknown mode #{mode}.")
	console.log("? mode=#{mode}")

$(window).load(() ->
	if m = location.search.match("b=(\\d+)")
		b = m[1]
		$("#preference input").each((a) ->
			$(@).prop("checked",b & $(@).val())
		)
		console.log("bit found #{m[1]}")

	$(@).on("orientationchange",() ->
		$("body").hide().css("opacity",0);
		draw(C_MODE_UNKNOWN)
		$("body")
		.show()
		.animate({"opacity":1.000},333,"easeOutCubic")
	)

	$("#header").html(location.hostname)
	$("img.thumbnail").each(() ->
		@src = $("<canvas width=1 height=1 />")[0].toDataURL()
	)

	id = 0;

	$("#frame")
	.prop("tabindex",0)
	.bind("click",-> jump(index + 1))
	.bind("contextmenu",-> jump(index - 1) && false)
	.bind("touchstart",(a) ->
		x = a.clientX
		y = a.clientY

		id = setTimeout("close()",1000)
	)
	.bind("touchend",(a) ->
		clearTimeout(id)
	)
	.bind("mousewheel",(a) ->
		if a.originalEvent.wheelDelta < 0
			jump(index + 1)
		else
			jump(index - 1)
	)
	.bind("DOMMouseScroll",(a) ->
		if a.originalEvent.detail > 0
			jump(index + 1)
		else
			jump(index - 1)
	)
	.keydown((a) ->
		switch a.keyCode
			when 0x25,0x22,0x6b,0x41,0x57
				jump(index + 1)
			when 0x27,0x21,0x6d,0x44,0x53
				jump(index - 1)
			when 0x23
				jump(list.length - 1)
			when 0x24
				jump(0)
			when 0x1b
				close()
			else
				console.log("keyCode 0x#{a.keyCode.toString(16)} (#{a.keyCode})")
	)
#	.bind("touchy-swipe",(b,c,a) ->
#		switch a.direction
#			when "left"
#				jump(index - 1)
#			when "right"
#				jump(index + 1)
#			else
#				alert(a)
#	)
	.touchwipe(
		wipeLeft:() -> jump(index - 1)
		wipeRight:() -> jump(index + 1)
	)

	$("#preference")
	#.prop("tabindex",1)
	.blur(() -> closepreference())

	$("#preference .field input")
	.click(() ->
		b = 0
		$("#preference input:checked").each((a) ->
			b |= $(@).val()
		)
		history.replaceState(null,null,"#{location.protocol}//#{location.hostname}#{location.pathname}?b=#{b}")
		console.log(b)
		console.log(2)
	)

	$("#preference .field").append(
		$("<div>")
		.addClass("input")
		.click(() ->
			$(@).parent().find("input").click()
		)
	)

	draw()

	$.getJSON("<?=getenv("SCRIPT_NAME")?>/?op=i",(a) ->
		for _ in a.value
			((a) ->
				$("#menu").append(
					$("#menu .cloak")
					.clone(1)
					.prop("class","book")
					.find(".thumbnail").css("background-image","url(\"<?=getenv("SCRIPT_NAME")?>/#{encodeURIComponent(_)}/?op=m\")").end()
					.find(".title span").html(a[2]).end()
					.find(".author span").html(a[1]).end()
					.click(() -> load(manga = a[0]))
				)
			)(_.match("^((?:Magica Quartet|[^\x00-\x20\x7F])+?) (.+?)$"))
	)

	m = "<?=getenv("PATH_INFO")?>".replace(/^\//,"").split("/")
	i = parseInt(location.hash.replace(/^#/,""))
	if m[0]?.length > 0
		load(manga = m[0])
	if m[1]?.length > 0
		read(null,m[1],i)
)

@load = (c) ->
	$(".volume").remove()

	$.getJSON("<?=getenv("SCRIPT_NAME")?>/#{encodeURIComponent(c)}/?op=i",(a) ->
		for _ in a.value
			((a) ->
				$("#book").append(
					$("#book .cloak")
					.clone(1)
					.prop("class","volume")
					.find(".thumbnail").css("background-image","url(\"<?=getenv("SCRIPT_NAME")?>/#{encodeURIComponent(c)}/#{encodeURIComponent(a)}/?op=m\")").end()
					.find("span:eq(0)").html(_).end()
					.click(() -> read(null,a))
				)
			)(_)
	)

	if (mode & C_MODE_MASK) == (C_MODE_PHONE|C_MODE_VERTICAL)
		$("#main").animate({"left":-$("#menu").width()},333,"easeOutCubic")
		$("#navi .a").show().animate({"opacity":1.000},333,"easeOutCubic")

	history.replaceState(null,null,"#{location.protocol}//#{location.hostname}/#{c}/#{location.search}")

@back = () ->
	$("#main").animate({"left":0},333,"easeOutCubic")
	$("#navi .a").animate({"opacity":0.000},333,"easeOutCubic",-> $(@).hide())

@read = (c = manga,n,i = 0) ->
	manga = c
	volume = n
	$.getJSON("<?=getenv("SCRIPT_NAME")?>/#{encodeURIComponent(c)}/#{n}/?op=i",(a) ->
		window.list = a.value
		open()
		jump(i);
	)
	history.replaceState(null,null,"#{location.protocol}//#{location.hostname}/#{c}/#{n}/#{location.search}##{i}")

@open = () ->
	preference.close()
	$("#frame")
	.show()
	.animate({"opacity":1.000},333,"easeOutCubic")
	.focus()

@close = () ->
	$("#image img:NOT(.thumbnail)").remove()
	$("#frame")
	#.animate({"opacity":0},333,"easeOutCubic",-> $(@).hide())
	.animate({"opacity":0},333,"easeOutCubic",->
		$(@).hide()
	)


@make = (i = index) ->
	if $("##{i}").size()
		console.log("? Image #{i} cached.")
	else
		$("#image").append(
			$("#image .thumbnail")
			.clone(1)
			.prop("id",i)
			.prop("class","")
			.css("background-image","url(\"<?=getenv("SCRIPT_NAME")?>/#{encodeURIComponent(manga)}/#{encodeURIComponent(volume)}/#{encodeURIComponent(@list[parseInt(i / (!!(b & F_OPTION_SPLIT) + 1))])}?op=g&s=#{b & F_OPTION_SPLIT}&p=#{i % 2}&w=#{document.body.clientWidth}&h=#{document.body.clientHeight}&c=#{b & F_OPTION_COMPRESS}\")")
		)
		console.log("? Image #{i} maked.")
	return $("##{i}")

@jump = (i) ->
	if 0 <= i < list.length
		$("#image img:NOT(.thumbnail)#{(":NOT(##{_})" for _ in [(i - C_CACHE_BEHIND)..(i + C_CACHE_AHEAD)]).join("")}").remove()
		switch i - index
			when 1
				make(i - 1).hide()
				make(i + 0).show()
				make(i + 1).hide()
				make(i + 2).hide()
				make(i + 3).hide()
			when -1
				make(i - 1).hide()
				make(i + 0).show()
				make(i + 1).hide()
				make(i + 2).hide()
				make(i + 3).hide()
			else
				make(i - 1).hide()
				make(i + 0).show()
				make(i + 1).hide()
				make(i + 2).hide()
				make(i + 3).hide()
		index = i
	else
		close()

	history.replaceState(null,null,"#{location.protocol}//#{location.hostname}/#{manga}/#{volume}/#{location.search}##{index}")
	1

@preference = new class
	open:() ->
		$("#preference")
		.focus()
		.show()
		.animate({"opacity":0.900},333,"easeOutCubic")
	close:() ->
		$("#preference")
		.animate({"opacity":0.000},333,"easeOutCubic",-> $(@).hide())
	toggle:() ->
		if $("#preference").css("display") == "none"
			@open()
		else
			@close()

@grep = () ->
	$(".book").each(() ->
		if $(@).find("span").html().indexOf($(".grep input").val()) != -1
			$(@).show()
		else
			$(@).hide()
	)
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
}

#navi .a, #conf .a {
	display: none;
	opacity: 0;
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

#navi span {
	font-weight: bold;
	background-color: #ffffff;
	background-clip: padding-box;
	margin-left: 1em;
	margin-right: 1em;
	padding: 0.25em;
	border: 1px #000000 solid;
	border-radius: 0.25em;
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
	z-index: -1;
}

.grep {
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
	width: 100%;
	height: 33%;
	color: #888888;
	font-size: 80%;
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

.thumbnail, #image img {
	display: block;
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
	height: 100%;
	background-color: #1f1f1f;
	opacity: 0;
	z-index: 1;
}

#image img {
	width: 100%;
	height: 100%;
	position: relative;
}
#image,#frame .thumbnail {
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
	width: 45%;
	min-width: 0%;
	//max-width: 90%;
	word-wrap: normal;
	height: 100%;
	float: left;
}
#preference .value {
	width: 10%;
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
	height: 10%;
}

#conf {
	bottom: 0;
}

#main {
	//margin-top: 32px;
	width: 100%;
	height: 80%;
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
</style>
</head>
<body>
<div id="frame">
	<div id="image">
		<img class="thumbnail">
	</div>
</div>
<div id="navi">
	<div class="a">
		<span class="valign" onclick="back()">&lt;&nbsp;Back</span>
	</div>
	<div class="c">
		<span class="valign" onclick="preference.toggle()">Preference</span>
	</div>
	<!--
	<div class="b">
		<span class="valign"></span>
	</div>
	-->
</div>
	<div id="preference">
		<div class="field">
			<div class="label valign">
				Display Mode
			</div>
			<div class="label valign">
				Desktop
			</div>
			<div class="value">
				<label>
					<input type="radio" name="255" value="1">
				</label>
			</div>
		</div>
		<div class="field">
			<div class="label valign">
			</div>
			<div class="label valign">
				Notebook
			</div>
			<div class="value">
				<label>
					<input type="radio" name="255" value="2">
				</label>
			</div>
		</div>
		<div class="field">
			<div class="label valign">
			</div>
			<div class="label valign">
				Phone
			</div>
			<div class="value">
				<label>
					<input type="radio" name="255" value="4">
				</label>
			</div>
		</div>
		<div class="field">
			<div class="label valign">
			</div>
			<div class="label valign">
				Tablet
			</div>
			<div class="value">
				<label>
					<input type="radio" name="255" value="8">
				</label>
			</div>
		</div>
		<div class="field">
			<div class="label valign">
			</div>
			<div class="label valign">
			</div>
			<div class="value">
				<label>
					<input type="checkbox" name="768" value="512">
				</label>
			</div>
		</div>
		<div class="field">
			<div class="label valign">
				Show Thumbnails
			</div>
			<div class="label valign">
			</div>
			<div class="value">
				<label>
					<input type="checkbox" name="65536" value="65536">
				</label>
			</div>
		</div>
		<div class="field">
			<div class="label valign">
				Compression/Minimize
			</div>
			<div class="label valign">
			</div>
			<div class="value">
				<label>
					<input type="checkbox" name="131072" value="131072">
				</label>
			</div>
		</div>
		<div class="field">
			<div class="label valign">
				Horizontal Split
			</div>
			<div class="label valign">
			</div>
			<div class="value">
				<label>
					<input type="checkbox" name="262144" value="262144">
				</label>
			</div>
		</div>
		<div class="cloak">
			<div class="label valign">
				Horizontal Split
			</div>
			<div class="label valign">
			</div>
			<div class="value">
				<label>
					<input type="checkbox" name="262144" value="262144">
				</label>
			</div>
		</div>
	</div>
<div id="main">
	<div id="menu">
		<div class="grep">
			<label>
				Grep:
				<input type="text" onChange="grep()" onKeyUp="grep()">
			</label>
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
</div>
</body>
</html>
