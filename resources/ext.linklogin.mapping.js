jQuery( function( $ ) {
  $('.userErrorEmpty').hide();
  $('.userErrorSpecial').hide();
  $('.userErrorExists').hide();
  let usernameNoError = true;
  let origin = window.location.origin;

  $(".dropdown-item.pages").click(function( e ) {
    e.preventDefault();
    const page = $(this).text();
    const user = $(this).parents("tr").attr("id");
    callMap(user, page);
  });

  $(".dropdown-item.user").click(function( e ) {
    e.preventDefault();
    const page = $(this).parents("tr").attr("id");
    const user = $(this).text();
    callMap(user, page);
  }); 

  $(".unlink.pages").click(function( e ) {
    e.preventDefault();
    const page = $(this).parents("tr").attr("id");
    const user = $(this).siblings("span").text();
    callUnmap(user,page);
  }); 

  $(".unlink.users").click(function( e ) {
    e.preventDefault();
    const user = $(this).parents("tr").attr("id");
    const page = $(this).siblings("span").text();
    callUnmap(user,page);
  }); 

  $(".edit").click(function( e ) {
    e.preventDefault();
    const user = $(this).parents().siblings("span").text();
    const editURL = origin + '/w' + '/index.php' + '/special:edituser' + '/' + user;
    window.open(editURL, '_blank');
  }); 

  $(".create").click(function() {
    let user = $(this).siblings("input").val();
    validateUsername(user);
    if( usernameNoError ) {
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
            $(".username").addClass("is-invalid");
            $(".userErrorExists").show();
            var errorMessage = postData.createaccount.message;
            throw errorMessage;
          } else {
            $(".username").addClass("is-valid");
          }
        })
        .done(function(){
          addGroupsToUser(user,page);
          console.log("added groups");
        })
        .done(function(){
          callMap(user, page);
          console.log("mapped user to page");
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
    $.post(mappingApiURL,
      {
      method: method,
      user: user,
      page: page
      },
      function(data, status) {
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
        location.reload();
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

  function validateUsername(user) {
    let username = user;
    let regex = new RegExp(/[^a-zA-Z0-9+\s]/);
    $(".username").removeClass("is-invalid");
    $(".userErrorEmpty").hide();
    $(".userErrorSpecial").hide();
    $(".userErrorExists").hide();
    if (username.length == "") {
      $(".userErrorEmpty").show();
      $(".username").addClass("is-invalid");
      usernameNoError = false;
      return false;
    } else {
      if( (regex).test(username) ) {
        $(".userErrorSpecial").show();
        $(".username").addClass("is-invalid");
        usernameNoError = false;
        return false;
      } else {
        usernameNoError = true;
      }
    }
  }
});