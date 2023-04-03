jQuery( function( $ ) {
	checkDropdownVisibility();
	let usernameNoError = true;
	let origin = window.location.origin;
	let location = window.location.href;  
	let baseApiURL = mw.config.get('wgServer') + mw.config.get('wgScriptPath') + '/api.php';
	let mappingApiURL = baseApiURL + '?action=llmapping&format=json&';

	//Create a new User and assign to Page and corresponding Groups
	$('#linklogin-body').on('submit', '.user-create', function( e ) {
		e.preventDefault();
		let user = $(this).find("input").val();
		let page = $(this).parents("tr").attr("id");
		validateUsername(user, page);
		if( usernameNoError ) {
			user = user.substr(0,1).toUpperCase()+user.substr(1);
			createAccount(user, page);
		};
	});

	//Map User to Page on Special:Link Login Users
	$('#linklogin-body').on('click', '.dropdown-item.pages', function( e ) {
		e.preventDefault();
		const page_name = $(this).text();
		const user = $(this).parents("tr").attr("id");
		const user_name = $(this).parents("tr").children().eq(0).children("span").html();
		var page = $(this).attr("id");
		page = page.split('-')[1];
		const destination = $(this).parents("td");
		callMap(user_name, page);
		insertPage(user, page_name, destination, page);
	});

	//Map Page to User on Special:Link Login Pages
	$('#linklogin-body').on('click', '.dropdown-item.user', function( e ) {
		e.preventDefault();
		const user = $(this).text();
		const page = $(this).parents("tr").attr("id");
		callMap(user, page);
		insertUser(user, page);
	}); 

	//Unlinking Users on Special:Link Login Pages
	$('#linklogin-body').on('click', '.unlink.users', (function( e ) {
		$(this).find('i').tooltip('dispose');
		e.preventDefault();
		const page = $(this).parents("tr").attr("id");
		const user = $(this).siblings("span").text();
		const method = "unmap";
		$.post(mappingApiURL,
			{
				method: method,
				user: user,
				page: page
			})
			.done(function( data, status ) {
				reloadFragment(page);
			});
	})); 

	//Unlinking Pages on Special:Link Login Users
	$('#linklogin-body').on('click', '.unlink.pages', (function( e ) {
		$(this).find('i').tooltip('dispose');
		e.preventDefault();
		const user_name = $(this).parents("tr").children().eq(0).children("span").html();
		const user = $(this).parents("tr").attr("id");
		const page_name = $(this).siblings("span").text();
		let page = $(this).parents("li").attr("id");
		page = page.split('-')[1];
		const method = "unmap";
		$.post(mappingApiURL,
			{
				method: method,
				user: user_name,
				page: page
			})
			.done(function( data, status ) {
				$("[id=listitem-"+page+"]").remove()
				if (!$("#"+user+"List").children('li').length){
					$("#"+user+"List").remove();
				}
				//add item back to dropdown list, sorted
				var itemList = [];
				$('.dropdown-menu.pageslist').first().children().each(function() {
					itemList.push($(this).text());
				});
				itemList.push(page_name);
				itemList.sort();
				index = itemList.indexOf(page_name);
				if( index == 0 ) {
					$('.dropdown-menu.pageslist').prepend('<a href="#" class="dropdown-item pages" id="dropdownitem-'+ page +'">' + page_name + '</a>');
				} else {
					$('.dropdown-menu.pageslist').each(function() {
						$(this).children(".dropdown-item.pages").eq(index - 1).after('<a href="#" class="dropdown-item pages" id="dropdownitem-'+ page +'">' + page_name + '</a>');
					})
				}
			})
			.done(function(){
				checkDropdownVisibility();
			});
	})); 

	//Open new Tab for Special:EditUser
	$('#linklogin-body').on('click','.edit', (function( e ) {
		e.preventDefault();
		const user = $(this).parents().siblings("span").text();
		const editURL = mw.config.get('wgServer') + mw.config.get('wgScript') + '/special:edituser' + '/' + user;
		window.open(editURL, '_blank');
	})); 

	$('#linklogin-body').on('click', '.copy.clipboard', (function( e ) {
		e.preventDefault();
		const copyLink = $(this).attr("id");
		navigator.clipboard.writeText(copyLink).then(function() {
		}, function(err) {
			console.error('Async: Could not copy text: ', err);
		});
	})); 

	function createAccount(user, page){
		$.get(baseApiURL,
			{
				action: 'query',
				meta: 'authmanagerinfo|tokens',
				amirequestsfor: 'create',
				type: 'createaccount',
				format: 'json'
			},
			function(data,status,xhr){
				var data = data.query.tokens.createaccounttoken;
				password = generateRandomPassword();
				$.post(baseApiURL, 
					{
						action: 'createaccount',
						createreturnurl: origin,
						createtoken: data,
						username: user,
						password: password,
						retype: password,
						format: 'json'
					},
					function(postData,postStatus,postXhr){
						if( postData.createaccount.status == "FAIL"){
							let messageExists = $("#messageExists").html();
							$("#"+page+"userError").html(messageExists);
							$("#"+page+"Inputfield").addClass("is-invalid");
							var errorMessage = postData.createaccount.message;
							throw errorMessage;
						} else {
							$("#"+page+"Inputfield").addClass("is-valid");
						}
					})
					.done(function(){
						addGroupsToUser(user,page);
					})
					.done(function(){
						callMap(user, page);
					})
					.done(function(){
						insertUser(user,page);
						var itemList = [];
						$('.dropdown-menu.userlist').first().children().each(function() {
							itemList.push($(this).text());
						});
						itemList.push(user);
						itemList.sort();
						index = itemList.indexOf(user);
						if( index == 0 ) {
							$('.dropdown-menu.userlist').prepend('<a href="#" class="dropdown-item user">' + user + '</a>');
						} else {
							$('.dropdown-menu.userlist').each(function() {
								$(this).children(".dropdown-item.user").eq(index - 1).after('<a href="#" class="dropdown-item user">' + user + '</a>');
							})
						}
					});
			});
	};

	function addGroupsToUser(user, page){
		let mappingApiURL = baseApiURL + '?action=llmapping&format=json&';
		const method = "setGroup";
		$.post(mappingApiURL,
			{
				method: method,
				user: user,
				page: page
			},
			function(data, status) {
			});
	}

	function callMap(user, page){
		let mappingApiURL = baseApiURL + '?action=llmapping&format=json&';
		const method = "map";
		return $.post(mappingApiURL,
			{
				method: method,
				user: user,
				page: page
			}, function(){});
	}

	function insertUser(user, page){
		$("#"+page+"User").replaceWith('<td id="' + page + 'User"><span>'+user+'</span>'+' '+'<a href="#"><i class="fa fa-pen edit"></i></a>'+'<a href="#" class="unlink users ml-2"><i class="fa fa-times"></i></a></td>');
	}

	function insertPage(user, page_name, destination, page){
		if ( $("#"+user+"List").length ) {
			$(destination).children('ul').append('<li id="listitem-' + page + '"><span>' + page_name + '</span><a href="#" class="unlink pages ml-2"><i class="fa fa-times"></i></a></li>');
		} else {
			$(destination).prepend('<ul id="' + user + 'List"><li id="listitem-' + page + '"><span>' + page_name + '</span><a href="#" class="unlink pages ml-2"><i class="fa fa-times"></i></a></li></ul>');
		}
		$("[id=dropdownitem-" + page + "]").remove();
		checkDropdownVisibility();
	}

	function reloadFragment(page){
		const fragment = page + 'User';
		$("#"+fragment).load(location + " #" + page + "Fragment");
	}

	function checkDropdownVisibility() {
		$('.dropdown-menu.pageslist').each(function () {
			if( !$(this).children('.dropdown-item').length ) {
				$('.dropdown-toggle.pages').hide();
			} else {
				$('.dropdown-toggle.pages').show();
			}
		});
	}

	function generateRandomPassword(){
		let length = 8;
		let password = "";
		charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
		for( let i = 0, n = charset.length; i < length; ++i ) {
			password += charset.charAt(Math.floor(Math.random() * n));
		}
		return password;
	}

	function validateUsername(user, page) {
		let username = user;
		let messageEmpty = $("#messageEmpty").html();
		let messageSpecial = $("#messageSpecial").html();
		//Regex looks for all special characters except spaces
		let regex = new RegExp(/[^a-zA-Z0-9+\s]/);
		$("#"+page+"userError").html('');
		if( username.length == 0 ) {
			$("#"+page+"userError").html(messageEmpty);
			$("#"+page+"Inputfield").addClass("is-invalid");
			usernameNoError = false;
			return false;
		} else {
			if( regex.test(username) ) {
				$("#"+page+"userError").html(messageSpecial);
				$("#"+page+"Inputfield").addClass("is-invalid");
				usernameNoError = false;
				return false;
			} else {
				usernameNoError = true;
			}
		}
	}
});
