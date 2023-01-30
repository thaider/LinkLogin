jQuery( function( $ ) {
  checkDropdownVisibility();
  let usernameNoError = true;
  let origin = window.location.origin;
  let location = window.location.href;  
  let mappingApiURL = origin + '/w' + '/api.php?action=llmapping&format=json&';

  //Map User to Page on Special:Link Login Users
  $('#linklogin-body').on('click', '.dropdown-item.pages', function( e ) {
    e.preventDefault();
    const page = $(this).text();
    const user = $(this).parents("tr").attr("id");
    const user_name = $(this).parents("tr").children().eq(0).children("span").html();
    callMap(user_name, page);
    insertPage(user,page);
  });

  //Map Page to User on Special:Link Login Pages
  $('#linklogin-body').on('click', '.dropdown-item.user', function( e ) {
    e.preventDefault();
    const page = $(this).parents("tr").attr("id");
    const user = $(this).text();
    callMap(user, page);
    insertUser(user, page);
  }); 

  //Unlinking Users on Special:Link Login Pages
  $('#linklogin-body').on('click', '.unlink.users', (function( e ) {
    e.preventDefault();
    const page = $(this).parents("tr").attr("id");
    const user = $(this).siblings("span").text();
    //$.when(callUnmap(user,page)).done(reloadFragment(page));
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
    e.preventDefault();
    const user_name = $(this).parents("tr").children().eq(0).children("span").html();
    const user = $(this).parents("tr").attr("id");
    const page = $(this).siblings("span").text();
    const method = "unmap";
    $.post(mappingApiURL,
      {
      method: method,
      user: user_name,
      page: page
      })
      .done(function( data, status ) {
        $("#item"+page).remove();
        if (!$("#"+user+"List").children('li').length){
          $("#"+user+"List").remove();
        }
        $('.dropdown-menu.pageslist').append('<a href="#" class="dropdown-item pages ' + page + '">' + page + '</a>');
      })
      .done(function(){
        checkDropdownVisibility();
      });
  })); 

  //Open new Tab for Special:EditUser
  $('#linklogin-body').on('click','.edit', (function( e ) {
    e.preventDefault();
    const user = $(this).parents().siblings("span").text();
    const editURL = origin + '/w' + '/index.php' + '/special:edituser' + '/' + user;
    window.open(editURL, '_blank');
  })); 

  //Create a new User and assign to Page and corresponding Groups
  $('#linklogin-body').on('click', '.create', function() {
    let user = $(this).siblings("input").val();
    let page = $(this).parents("tr").attr("id");
    validateUsername(user, page);
    if( usernameNoError ) {
      user = user.substr(0,1).toUpperCase()+user.substr(1);
      createAccount(user, page);
    };
  }); 
   

  function createAccount(user, page){
    let url = origin + '/w' + '/api.php';
    $.get(url,
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
        $.post(url, 
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
          console.log("Added all Groups correlating to Page " + page + " to User " + user);
        })
        .done(function(){
          callMap(user, page);
          console.log("mapped user to page");
        })
        .done(function(){
          insertUser(user,page);
          $(".dropdown-menu").append('<a href="#" class="dropdown-item user" testseite"="">' + user + '</a>');
          console.log("updated view");
        });
      });
    };

  function addGroupsToUser(user, page){
    let mappingApiURL = origin + '/w' + '/api.php?action=llmapping&format=json&';
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
    let mappingApiURL = origin + '/w' + '/api.php?action=llmapping&format=json&';
    const method = "map";
    return $.post(mappingApiURL,
      {
      method: method,
      user: user,
      page: page
      }, function(){
        console.log("Mapped User " + user + " to Page " + page);
      });
  }

  function insertUser(user,page){
    $("#"+page+"User").replaceWith('<td id="'+page+'User"><span>'+user+'</span>'+' '+'<a href="#"><i class="fa fa-pen edit"></i></a>'+'<a href="#" class="unlink users" style="float:right">' + '&times;' + '</a></td>');
  }

  function insertPage(user,page){
    if ( $("#"+user+"List").length ) {
      $("#"+user+"Pages > ul").append('<li id="item' + page + '"><span>' + page + '</span><a href="#" class="unlink pages" style="float:right">' + '&times;' + '</a></li>');
    } else {
      $("#"+user+"Pages").prepend('<ul id="' + user + 'List"><li id="item' + page + '"><span>' + page + '</span><a href="#" class="unlink pages" style="float:right">' + '&times;' + '</a></li></ul>');
    }
    $(".dropdown-item.pages." + page).remove();
    checkDropdownVisibility();
  }

  function reloadFragment(page){
    let fragment = page + "User";
    $("#"+fragment).load(location+ " #" + page + "Fragment");
    console.log("Reloaded Fragment");
  }

  /*
  function callUnmap(user, page){
    let mappingApiURL = origin + '/w' + '/api.php?action=llmapping&format=json&';
    const method = "unmap";
    $.post(mappingApiURL,
    {
    method: method,
    user: user,
    page: page
    },
    function(data, status) {
    });
  }
  */

  function checkDropdownVisibility() {
    $('.dropdown-menu.pageslist').each(function () {
      if (!$(this).children('.dropdown-item').length) {
        $('.btn.btn-secondary.dropdown-toggle').hide();
        console.log("Dropdowns hidden");
      } else {
        $('.btn.btn-secondary.dropdown-toggle').show();
        console.log("Dropdowns shown");
      }
    });
  }

  function generateRandomPassword(){
    let length = 8;
    let password = "";
    charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    for (let i = 0, n = charset.length; i < length; ++i) {
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
    if (username.length == 0) {
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