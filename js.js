  var XMLHttpRequest = XMLHttpRequest || require('xmlhttprequest').XMLHttpRequest;

  var pages_scaned = 0;
  var lines_readed = 0;
  var total_pages = 0;
  var total_lines;
  var lines;
  var request_new_line;

window.onload = function() {

  var timer;

  document.getElementById('scan_file').onchange = function(){

    var file = this.files[0];
    var reader = new FileReader();

    reader.onload = function(){
      lines = this.result.split(/\r\n|\n/);
      total_lines = lines.length;

      clearContent();
      loopGetResults();
      showStatus();
    };

    reader.readAsText(file);
  };
}

function loopGetResults() {

  if (lines_readed < total_lines) {
    request_new_line = true;

    var request_data = setTimeout( function() {
      getResults(lines[lines_readed]);
    }, 3000);
  } else {
    clearTimeout(request_data);
  }

}

function getResults(q) {

  timer = setInterval(function() {
    startXMLRequest(window.location.pathname + 'index.php?action=data&q=' + q + '&p=' + pages_scaned, 'GET', '', function () {
      var responseData = JSON.parse(this.responseText);
      total_pages = responseData.pages;

      if (responseData.captcha) {
        clearInterval(timer);

        checkCaptcha(responseData.captcha, q);
      } else {
        insertContent(responseData.data);

        pages_scaned++;

        if (pages_scaned === total_pages) {
          lines_readed++;

          showStatus();
          saveContent(q);
          clearContent();
          clearInterval(timer);

          loopGetResults();
        }
      }

    });
  }, 3000);

}

function checkCaptcha(image, q) {

  var target = document.querySelector(".content");

  var captcha_content = document.createElement('div');
  captcha_content.id = 'captcha';
  captcha_content.innerHTML = captchaForm(image);

  var fragment = document.createDocumentFragment();
  fragment.appendChild(captcha_content);

  target.appendChild(fragment);

  submit_captcha = document.getElementById('submit_captcha');

  submit_captcha.addEventListener('click', function() {
    startXMLRequest(window.location.pathname + 'index.php?action=captcha&c=' + document.getElementById('captcha_value').value + '&q=' + q + '&p=' + pages_scaned, 'GET', '', function () {
      captcha_content.remove();
      getResults(q);
    });
  });

}

function captchaForm(image) {
  return '<img src="http://www.annu.com/' + image + '" /><input type="text" id="captcha_value" /><input type="submit" name="submit_captcha" id="submit_captcha" value="send captcha" />';
}

function clearContent() {
  pages_scaned = total_pages = 0;
  document.getElementsByTagName('tbody')[0].innerHTML = '';
}

function saveContent(q) {
  var data = new Array();

  var table_headers = document.getElementsByTagName('thead')[0];
  var table_content = document.getElementsByTagName('tbody')[0];

  for(var i = 0; i < table_content.rows.length; i++) {
    var row = {};

    for(var j = 0; j < table_headers.rows[0].cells.length; j++) {
      row[table_headers.rows[0].cells[j].innerText] = table_content.rows[i].cells[j].innerText;
    }

    data.push(row);
  }

  data = {'q': q, 'data': data};

  console.log(JSON.stringify(data));

  startXMLRequest(window.location.pathname + 'index.php?action=save', 'POST', JSON.stringify(data));
}

function insertContent(data) {
  for (var key in data) {
    var newRow = document.getElementById('contacts').getElementsByTagName('tbody')[0].insertRow();

    var cell1 = newRow.insertCell(0);

    var cell1Text  = document.createTextNode(data[key].name);
    cell1.appendChild(cell1Text);

    var cell2   = newRow.insertCell(1);

    var cell2Text  = document.createTextNode(data[key].address);
    cell2.appendChild(cell2Text);

    var cell3   = newRow.insertCell(2);

    var cell3Text  = document.createTextNode(data[key].phone);
    cell3.appendChild(cell3Text);
  }
}

function showStatus() {
  document.getElementById('search_status').innerHTML = lines_readed + ' / ' + total_lines + ' (' + lines[lines_readed] + ')';
}

function startXMLRequest (url, type, json, callback) {

  var xmlRequest = createXMLRequest();

  xmlRequest.open(type, url, true);
  xmlRequest.setRequestHeader('Accept', 'application/json');
  //xmlRequest.setRequestHeader("Content-Type", "application/json;charset=UTF-8");

  xmlRequest.onreadystatechange = function () {
    if (isXMLFinished(xmlRequest)) {
      if (typeof callback === "function") {
        callback.apply(xmlRequest);
      }
    }
  }

  xmlRequest.send(json);

}

function createXMLRequest () {

  var xmlRequest;

  if (XMLHttpRequest) {
    xmlRequest = new XMLHttpRequest();
  } else {
    xmlRequest = new ActiveXObject('Microsoft.XMLHTTP');
  }

  return xmlRequest;

}

function isXMLFinished (xmlRequest) {
  return (xmlRequest.readyState === 4) && (xmlRequest.status === 200);
}
