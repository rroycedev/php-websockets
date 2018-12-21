<?php 
$colors = array('#007AFF','#FF7000','#FF7000','#15E25F','#CFC700','#CFC700','#CF1100','#CF00BE','#F00');
$color_pick = array_rand($colors);
?>

<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style type="text/css">
.chat-wrapper {
	font: bold 11px/normal 'lucida grande', tahoma, verdana, arial, sans-serif;
    background: #00a6bb;
    padding: 20px;
    margin: 20px auto;
    box-shadow: 2px 2px 2px 0px #00000017;
	min-width:500px;
}
#message-box {
    width: 97%;
    display: inline-block;
    height: 300px;
    background: #fff;
    box-shadow: inset 0px 0px 2px #00000017;
    overflow: auto;
    padding: 10px;
}
.user-panel{
    margin-top: 10px;
}
input[type=text]{
    border: none;
    padding: 5px 5px;
    box-shadow: 2px 2px 2px #0000001c;
}
input[type=text]#name{
    width:20%;
}
input[type=text]#message{
    width:60%;
}
button#send-message, button#connect {
    border: none;
    padding: 5px 15px;
    background: #11e0fb;
    box-shadow: 2px 2px 2px #0000001c;
}
</style>
</head>
<body>

<div class="chat-wrapper">
<input type="text" name="fromname" id="fromname" placeholder="Username" maxlength="30" />&nbsp;<button id="connect" name="connect">Connect</button>
<div id="message-box" style="margin-top: 10px;"></div>
<div class="user-panel">
<input type="text" name="toname" id="toname" placeholder="Destination Username" maxlength="30" disabled />
<input type="text" name="message" id="message" placeholder="Type your message here..." disabled maxlength="100" />
<button id="send-message" name="send-message" disabled>Send</button>
</div>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
<script language="javascript" type="text/javascript">  
	var connected = false;

	$('#send-message').click(function(){
		send_message();
	});

	$('#connect').click(function() {
                var name_input = $('#fromname'); //user name

                if(name_input.val() == ""){ //empty name?
                        alert("Enter your Name please!");
                        return;
                }

	        var msgBox = $('#message-box');
        	var wsUri = "ws://10.218.155.47:9000/server.php?user=" + name_input.val();

	        var authToken = 'R3YKZFKBVi';

        	document.cookie = 'X-Authorization=' + authToken + '; path=/';

	        websocket = new WebSocket(wsUri);

	        websocket.onopen = function(ev) { // connection is open
	        }
        	// Message received from server
	        websocket.onmessage = function(ev) {
        	        var response            = JSON.parse(ev.data); //PHP sends Json data

	                var res_type            = response.type; //message type
        	        var user_message        = response.message; //message text
                	var user_name           = response.name; //user name
	                var user_color          = response.color; //color
        	        switch(res_type){
                	        case 'usermsg':
	                                msgBox.append('<div><span class="user_name" style="color:' + user_color + '">' + user_name + '</span> : <span class="user_message">' + user_message + '</span></div>');
        	                        break;
                	        case 'success':
		                        msgBox.append('<div class="system_msg" style="color:#bbbbbb">Welcome to my "Demo WebSocket Chat box"!</div>'); //notify user
	                                msgBox.append('<div style="color:#bbbbbb">' + user_message + '</div>');
					$('#connect').text('Disconnect');
					connected = true;
					$('#fromname').prop('disabled', true);
					$('#toname').prop('disabled', false);
					$('#message').prop('disabled', false);
					$('#send-message').prop('disabled', false);
        	                        break;
                	        case 'failure':
                        	        msgBox.append('<div style="color:#ff0000">ERROR: ' + user_message + '</div>');
	                                break;
        	        }
	                msgBox[0].scrollTop = msgBox[0].scrollHeight; //scroll message
        	};

	        websocket.onerror       = function(ev){
        	        msgBox.append('<div class="system_error">Error Occurred - ' + ev.data + '</div>');
	        };
        	websocket.onclose       = function(ev){ msgBox.append('<div class="system_msg">Connection Closed</div>'); };

	});
	
	//User hits enter key 
	$( "#message" ).on( "keydown", function( event ) {
	  if(event.which==13){
		  send_message();
	  }
	});
	
	//Send message
	function send_message(){
		var message_input = $('#message'); //user message text
		var name_input = $('#toname'); //user name
		
		if(message_input.val() == ""){ //empty name?
			alert("Enter your Name please!");
			return;
		}
		if(message_input.val() == ""){ //emtpy message?
			alert("Enter Some message Please!");
			return;
		}
		//prepare json data
		var msg = {
			message: message_input.val(),
			name: name_input.val(),
			color : '<?php echo $colors[$color_pick]; ?>'
		};
		//convert and send data to server
		websocket.send(JSON.stringify(msg));	
		message_input.val(''); //reset message input
	}
</script>
</body>
</html>

