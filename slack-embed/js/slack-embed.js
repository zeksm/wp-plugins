(function(){
    
    function makeRequest(url, callback, args) {
        
        try {
            
            var httpRequest = new XMLHttpRequest();

            if (!httpRequest) {
                console.log('Cannot create an XMLHTTP instance');
                return false;
            }
            
            httpRequest.onreadystatechange = function() {
                if (httpRequest.readyState === XMLHttpRequest.DONE) {
                    if (httpRequest.status === 200) {
                        var response = httpRequest.responseText;
                        callback(response, args);
                    } else {
                        console.log("AJAX call failed, status: " + httpRequest.status);
                    }
                }
            };
            
            httpRequest.open('GET', url + "&timestamp=" + Date.now());
            httpRequest.setRequestHeader('Cache-Control', 'no-cache');
            httpRequest.send();
            
        } catch(e) {
          console.log('Caught exception while making AJAX call: ' + e.description);
        }
        
    }
    
    function fillDiv(response, args) {
        var slack = args;
        var response = JSON.parse(response);
        var messages = response["messages"];
        var users = response["users"];
        messages.reverse();
        var messageContainer = document.createElement("div");
        var lastDate = "";
        messages.forEach(function(message) {
            var singleMessage = document.createElement("div");
            singleMessage.className = "slack-single-message";
            var user = message.user;
            var userInfo = users[user]
            var userName = userInfo["realName"];
            var avatarLink = userInfo["avatarLink"];
            var text = message.text;
            var textUserIDs = slackRegEx.exec(text);
            if (textUserIDs !== null) {
                textUserIDs.shift();
                textUserIDs.forEach(function(elem) {
                    var match = "<@" + elem + ">";
                    var name = users[elem]["realName"];
                    text = text.replace(match, name);
                });
            }
            var timestamp = parseInt(message.ts);
            var dateTime = new Date(timestamp * 1000);
            var dateString = dateTime.toDateString();
            if (dateString != lastDate) {
                var dateLabel = document.createElement("div");
                dateLabel.className = "slack_embed_date";
                dateLabel.textContent = dateString;
                messageContainer.appendChild(dateLabel);
                lastDate = dateString;
            }
            var timeString = zeroed(dateTime.getHours()) + ":" + zeroed(dateTime.getMinutes());
            singleMessage.innerHTML = "<span class='slack-message-avatar'><img src='" + avatarLink + "'></span><span class='slack-message-timestamp'>" + timeString + "</span><span class='slack-message-user'>" + userName + "</span><div class='slack-message-text'>" + text + "</div><div style='clear: both;'></div>";
            messageContainer.appendChild(singleMessage);
        });
        slack.innerHTML = "";
        slack.appendChild(messageContainer);
    }
    
    function zeroed(str) {
        str = "00" + str;
        str = str.slice(-2);
        return str
    }
    
    var userNames = [];
    var intervals = [];
    var slackElems = [];
    var slackRegEx = /<@(.*?)>/g;
    
    var slackDivs = document.querySelectorAll(".slack_channel_embed");
    for (var i=0, imax=slackDivs.length; i<imax; i++) {
        var slack = slackDivs[i];
        var id = slack.id;
        var channel = id.split("-")[1];
        slackElems[channel] = slack;
        console.log(channel);
        var intervalFunc = (function(channel, slack) {
            return function() {
                makeRequest(ajaxurl + "?action=check_channel&channel=" + channel, fillDiv, slack);
            }
        })(channel, slack)
        intervalFunc();
        setInterval(intervalFunc, 10000);
    }
  
})();

(function(){
    
    var tabLabels = document.querySelectorAll(".slack_embed_tabs_label");
    
    for (var i = 0, imax = tabLabels.length; i < imax; i++) {
        
        var tabLabel = tabLabels[i];
        
        tabLabel.addEventListener("click", function(e) {
            
            var targetLabel = e.currentTarget;
            
            if (!targetLabel.classList.contains("slack_embed_tabs_label_selected")) {
                
                var id = targetLabel.id;
                var channel = id.split("-")[1];
                
                var tabContainer = targetLabel.parentElement.parentElement;
                var currentLabel = tabContainer.querySelector(".slack_embed_tabs_label_selected");
                var currentEmbed = tabContainer.querySelector(".slack_channel_embed_selected");
                var targetEmbed = tabContainer.querySelector("#slack-" + channel);
                
                currentLabel.classList.remove("slack_embed_tabs_label_selected");
                currentEmbed.classList.remove("slack_channel_embed_selected");
                targetLabel.classList.add("slack_embed_tabs_label_selected");
                targetEmbed.classList.add("slack_channel_embed_selected");
                
            }
        })
    }

})();