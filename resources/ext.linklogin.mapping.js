jQuery( function( $ ) {
  $("#usercheck").hide();
  let usernameNoError = true;
  let origin = window.location.origin;

  $(".dropdown-item.pages").click(function() {
    const page = $(this).text();
    const user = $(this).parents("tr").attr("id");
    callMap(user, page);
  });

  $(".dropdown-item.user").click(function() {
    const page = $(this).parents("tr").attr("id");
    const user = $(this).text();
    callMap(user, page);
  }); 

  $(".unlink.pages").click(function() {
    const page = $(this).parents("tr").attr("id");
    const user = $(this).siblings("span").text();
    callUnmap(user,page);
  }); 

  $(".unlink.users").click(function() {
    const user = $(this).parents("tr").attr("id");
    const page = $(this).siblings("span").text();
    callUnmap(user,page);
  }); 

  $(".edit").click(function() {
    const user = $(this).siblings("span").text();
    const editURL = origin + '/w' + '/index.php' + '/special:edituser' + '/' + user;
    $(location).attr('href', editURL);
  }); 


  $(".create").click(function() {
    validateUsername();
    if( usernameNoError ) {
      let user = $("#username_input").val();
      user = user.substr(0,1).toUpperCase()+user.substr(1);
      let page = $(this).parents("tr").attr("id");
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
        //console.log(data,status,xhr)
        var data = data.query.tokens.createaccounttoken;
        $.post(url, 
        {
          action: 'createaccount',
          createreturnurl: origin,
          createtoken: data,
          username: user,
          password: 'thisisanexample',
          retype: 'thisisanexample',
          format: 'json'
        },
        function(postData,postStatus,postXhr){
          //console.log(postData);
          if( postData.createaccount.status == "FAIL"){
            var errorMessage = postData.createaccount.message;
            alert(errorMessage);
            throw errorMessage;
          }
        })
        .done(function(){
          callMap(user, page);
          console.log("called");
        });
      });
    };

  function callMap(user, page){
    let mappingApiURL = origin + '/w' + '/api.php?action=llmapping&format=json&';
    const method = "map";
    $.post(mappingApiURL,
      {
      method: method,
      user: user,
      page: page
      },
      function(data, status) {
        //console.log(method, user, page);
        location.reload();
    });
  }

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
        //console.log(method, user, page);
        location.reload();
      });
  }

  function validateUsername() {
    let username = $("#username_input").val();
    let regex = new RegExp(/[^a-zA-Z0-9+\s]/);
      if (username.length == "") {
        $("#usercheck").show();
        usernameNoError = false;
        return false;
      } /*else if (username.length < 3 || username.length > 10) {
        $("#usercheck").show();
        $("#usercheck").html("**length of username must be between 3 and 10");
        usernameNoError = false;
        return false;
      }*/else if( (regex).test(username) ) {
        $("#usercheck").show();
        $("#usercheck").html("**must not contain any special characters");
        usernameNoError = false;
        return false;
      } else {
        $("#usercheck").hide();
      }
    }

});