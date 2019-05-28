function phpDfBot() {
    //return null; //TODO: remover
    try {
        var threads = GmailApp.search("(list:nvagas@googlegroups.com OR list:leonardoti@googlegroups.com OR list:clubinfobsb@googlegroups.com OR to:nvagas@googlegroups.com OR to:vagas@noreply.github.com OR to:clubinfobsb@googlegroups.com OR to:leonardoti@googlegroups.com) is:unread", 0, 25);
        Logger.log([threads]);
        return null; //TODO: remover
        //AND (subject:((desenvolvedor* OR programador* OR developer* OR analista* OR php OR web OR arquiteto* OR dba OR suporte)) OR (desenvolvedor* OR programador* OR developer* OR analista* OR php OR web OR arquiteto*))
        //var threads = GmailApp.search("from:anacristina.limafernandes@gmail.com", 0, 25);
        var pattern = /<img.*src="([^"]*)"[^>]*>/;
        var label = GmailApp.getUserLabelByName("STILL_UNREAD");
        var labelBot = GmailApp.getUserLabelByName("ENVIADO_PRO_BOT");
        var patternContent = /(desenvolv(edor|imento)| ti | ti|programador|developer|analista|php|web|arquiteto|dba|suporte|devops|teste|banco de dados|segurança da informação|dev-ops|designer|front-end|back-end|scrum|de dados|tecnologia|de projetos|infra|engesoftware|oportunidade|clubinfo|software|hardware)/i;
        Logger.log(["THREADS:", threads.length]);
        for (var key in threads) {
            var thread = threads[key];
            var messages = thread.getMessages();
            var payload = {};
            Logger.log(["MSGS:", messages.length]);
            for (var index in messages) {
                var message = messages[index];
                var attachments = message.getAttachments();
                var texto = message.getPlainBody()
                    .split('You are receiving this because you are subscribed to this thread')[0]
                    .split('Você recebeu esta mensagem porque está inscrito para o Google')[0]
                    .split('Você está recebendo esta mensagem porque')[0]
                    .split('Esta mensagem pode conter informa')[0]
                    .split('Você recebeu esta mensagem porque')[0]
                    .split('Antes de imprimir')[0]
                    .split('This message contains')[0]
                    .split('NVagas Conectando')[0]
                    .split('cid:image')[0]
                    .split('Atenciosamente')[0]
                    .split('Att.')[0]
                    .split('Att,')[0]
                    .split('AVISO DE CONFIDENCIALIDADE')[0]
                ;
                Logger.log(["SUBJECT:", message.getSubject()]);
                if (texto.search(patternContent) !== -1 || message.getSubject().search(patternContent) !== -1) {
                    Logger.log(["TEXTO:", texto]);
                    payload[thread.getId()] = {
                        subject: message.getSubject()
                    };
                    var html = message.getBody()
                        .split('bp.blogspot.com')[0];
                    var matches = pattern.exec(html);
                    var image = null;
                    var url = '';
                    if (matches) {
                        url = matches[1];
                        var urlPattern = /^https*\:\/\/.*$/;
                        if (!urlPattern.exec(url)) {
                            url = '';
                        }
                    }
                    if (texto && texto.length > 200) {
                        Logger.log(texto);
                        //var data = thread.getLastMessageDate().toISOString().substr(0, 10);  //.split('-').reverse().join('/');
                        payload[thread.getId()].message = texto.replace(/^(:)/gi, '').trim();
                    }
                    payload[thread.getId()].image = [];
                    if (url.length) {
                        payload[thread.getId()].image.push(url);
                    }
                    if (attachments.length) {
                        for (var chave in attachments) {
                            var blob = attachments[chave].copyBlob();
                            payload[thread.getId()].image.push(Utilities.base64Encode(blob.getBytes()));
                        }
                    }
                }
            }
            if (Object.keys(payload).length) {
                sendMessage(payload);
                thread.markRead();
                thread.moveToTrash();
                thread.removeLabel(label);
                thread.addLabel(labelBot);
            }
        }
    } catch (e) {
        Logger.log(['ERRO', e.message]);
    }
}

function sendMessage(payload) {
    var url = 'https://marcelorodovalho.com.br/dsv/workspace/phpdfbot/public/index.php/sendChannelMessage';
    var options = {
        "method": "POST",
        "contentType": "application/json",
        "payload": JSON.stringify(payload),
        "followRedirects": true,
        "muteHttpExceptions": true
    };
    if (Object.keys(payload).length) {
        var result = UrlFetchApp.fetch(url, options);
        Logger.log(result.getResponseCode());
        if (result.getResponseCode() == 200) {
            var params = JSON.parse(result.getContentText());
            Logger.log(params.results);
        } else {
            var params = JSON.parse(result.getContentText());
            throw new Error(params.results);
        }
    }
}

function getCrawler() {
    return null; //TODO: remover
    var url = 'https://marcelorodovalho.com.br/dsv/workspace/phpdfbot/public/crawler';
    var options = {
        "method": "GET",
        "followRedirects": true,
        "muteHttpExceptions": true
    };
    var result = UrlFetchApp.fetch(url, options);
    //var result = urlFetchWihtoutError(url, options);
    Logger.log(result);
    if (result.getResponseCode() == 200) {
        var params = JSON.parse(result.getContentText());
        Logger.log(params.results);
    }
}

function testing() {
    /*var sheet   = SpreadsheetApp.openByUrl('https://docs.google.com/spreadsheets/d/1VWzvU7MqrNislFZz6FR1CXYiaVi90l54DIXVDosUEyw/edit#gid=0').getActiveSheet();
    var row     = 2;
    sheet.getRange(2, 1, sheet.getMaxRows() - 1, 4).clearContent();
    var label   = sheet.getRange("F3").getValue();
    var pattern = sheet.getRange("F4").getValue();
    Logger.log(label);
    Logger.log(pattern);*/

    var threads = GmailApp.search("(list:nvagas@googlegroups.com OR list:gebeoportunidades@googlegroups.com OR list:leonardoti@googlegroups.com OR list:clubinfobsb@googlegroups.com OR to:nvagas@googlegroups.com OR to:gebeoportunidades@googlegroups.com OR to:vagas@noreply.github.com OR to:clubinfobsb@googlegroups.com OR to:leonardoti@googlegroups.com) is:unread", 0, 50);
    Logger.log(threads);
    Logger.log(threads.length);
    for (var key in threads) {
        var thread = threads[key];
        var messages = thread.getMessages();
        for (var index in messages) {
            var message = messages[index];
            Logger.log(message.getSubject());
        }
    }
}

function notify() {
    return null; //TODO: remover
    var url = 'https://marcelorodovalho.com.br/dsv/workspace/phpdfbot/public/notify';
    var options = {
        "method": "GET",
        "followRedirects": true,
        "muteHttpExceptions": true
    };
    var result = UrlFetchApp.fetch(url, options);
    Logger.log(result.getResponseCode());
    if (result.getResponseCode() == 200) {
        var params = JSON.parse(result.getContentText());
        Logger.log(params.results);
    }
}

function urlFetchWihtoutError(url, options) {
    const NB_RETRY = 10;
    var nbSecPause = 1.5;
    var nbErr = 0;
    while (nbErr < NB_RETRY) {
        try {
            if (nbErr > 0) Logger.log('Error number:' + nbErr);
            var res = UrlFetchApp.fetch(url, options);
            return res;
        } catch (error) {
            nbErr++;
            Logger.log('Error: ' + error.message + " ---> Let's pause the system during " + nbSecPause + " seconds. Loop number: " + nbErr)
            Utilities.sleep(nbSecPause * 1000)
            nbSecPause += 0.5;
        }
    }
}