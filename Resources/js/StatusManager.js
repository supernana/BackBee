var bb = (bb) ? bb : {};

bb.StatusManager = (function($,gExport){
    var instance = null;
    var pageWebservice = null;
    var contentWebservice = null;
    var currentPage = null;
    var popupDialog = null;
    var modified = false;
    
    var _settings = {
        states: {
            offline: 0,
            online: 1,
            hidden: 2,
            deleted: 4
        },
        webServicesInfos:  {
            pageWs : "ws_local_page",
            revisionWs :"ws_local_revision"
        },
        pageId: null
    };
    
    var _init = function(userSettings){
        var userConfig = userConfig || {};
        $.extend(true,_settings,userSettings);
        
        _initWebservice();
        _initDialogs();
        
        instance = _publicApi;
        
        return instance;
    };
    
    var _initWebservice = function(){
        pageWebservice = bb.webserviceManager.getInstance(_settings.webServicesInfos.pageWs);
        revisionWebservice = bb.webserviceManager.getInstance(_settings.webServicesInfos.revisionWs);
    };
    
    var _initDialogs = function(){
        popupDialog = bb.PopupManager.init({
            dialogSettings:{
                modal: true
            }
        });
    };
    
    var _getOnline = function() {
        return (currentPage.state & _settings.states.online);
    };
    
    var _setOnline = function(online) {
        if (true == online && !(currentPage.state & _settings.states.online)) {
            currentPage.state = currentPage.state + _settings.states.online;
            modified = true;
        } else if (false == online && (currentPage.state & _settings.states.online)) { 
            currentPage.state = currentPage.state - _settings.states.online;
            modified = true;
        }
    };
    
    var _getHidden = function() {
        return (currentPage.state & _settings.states.hidden);
    };
    
    var _setHidden = function(hidden) {
        if (true == hidden && !(currentPage.state & _settings.states.hidden)) {
            currentPage.state = currentPage.state + _settings.states.hidden;
            modified = true;
        } else if (false == hidden && (currentPage.state & _settings.states.hidden)) { 
            currentPage.state = currentPage.state - _settings.states.hidden;
            modified = true;
        }
    };

    var _getPublishingDate = function() {
        date = new Date();
        return (null == currentPage.publishing) ? null : date.setTime(currentPage.publishing*1000);
    };

    var _getArchivingDate = function() {
        date = new Date();
        return (null == currentPage.archiving) ? null : date.setTime(currentPage.archiving*1000);
    };
    
    var _setPublishingDate = function(date) {
        if (null != date) date = date.getTime() / 1000;
        if (currentPage.publishing != date) {
            currentPage.publishing = date;
            modified = true;
        }
    };

    var _setArchivingDate = function(date) {
        if (null != date) date = date.getTime() / 1000;
        if (currentPage.archiving != date) {
            currentPage.archiving = date;
            modified = true;
        }
    };

    var _setMetadata = function(metadata) {
        currentPage.metadata = metadata;
        modified = true;
    };
    
    var _setTitle = function(title) {
        if ('' != title && title != currentPage.title) {
            currentPage.title = title;
            modified = true;            
        }
    };
    
    var _edit = function() {
        var context = $('#bb5-dialog-treeview').bbPageBrowser('getContext');
        $('#bb5-dialog-treeview').bbPageBrowser('editPage', bb.frontApplication.getPageId(), null);
    };
    
    var _remove = function() {
        var confirmDialog = popupDialog.create("confirmDialog", {
            title:bb.i18n.__('statusmanager.page_removal'),
            buttons:{
                "Delete" :  {
                    text: bb.i18n.__('popupmanager.button.delete'),
                    click: function() {
                        var confirmDialog = this;
                        $(confirmDialog).parent().mask(bb.i18n.loading);
                        pageWebservice.request('delete', {
                            params: {
                                uid: bb.frontApplication.getPageId()
                            },
                            success: function(response) {
                                $(confirmDialog).dialog("close");
                                if (response.result) {
                                    document.location = bb.baseurl+response.result.url;
                                } else {
                                    _displayError(bb.i18n.__('statusmanager.error.deleting_page'), null, _remove);
                                }
                            },
                            error: function(response) {
                                $(confirmDialog).dialog("close");
                                _displayError(bb.i18n.__('statusmanager.error.deleting_page'), response.error, _remove);
                            }
                        });
                    }
                },
                "Cancel": {
                    text: bb.i18n.__('popupmanager.button.cancel'),
                    click: function() {
                        $(this).dialog("close");
                        return false;
                    }
                }
            }
        });
        $(confirmDialog.dialog).html(bb.i18n.__('statusmanager.delete_page'));
        confirmDialog.show();
    };
    
    var _enable = function() {
        if (!currentPage) {
            pageWebservice.request('find', {
                params:{
                    uid: _settings.pageId
                },
                success:function(response){
                    if (response.result) {
                        currentPage = response.result;
                        if (null == currentPage.publishing) currentPage.publishing = 0;
                        if (null == currentPage.archiving) currentPage.archiving = 0;
                        $('#bb5-status').trigger('page:onload');
                    } else {
                        _displayError(bb.i18n.__('statusmanager.error.loading_page'), null, _enable);
                    }
                },
                error:function(response){
                    _displayError(bb.i18n.__('statusmanager.error.loading_page'), response.error, _enable);
                    throw response.error;
                }
            });
        }
    };
    
    var _disable = function(callback, arg) {
        if (modified) {
            var confirmDialog = popupDialog.create("confirmDialog",{
                title: bb.i18n.__('statusmanager.page_modified'),
                buttons:{
                    "Save" : {
                        text: bb.i18n.__('popupmanager.button.save'),
                        click: function(){
                            _update();
                            $(this).dialog("close");
                            return (callback) ? callback(arg) : false;
                        }
                    },
                    "Cancel": {
                        text: bb.i18n.__('popupmanager.button.cancel'),
                        click: function(a){
                            currentPage = null;
                            $(this).dialog("close");
                            return false;
                        }
                    }
                }
            });
            $(confirmDialog.dialog).html(bb.i18n.__('statusmanager.save_page'));
            confirmDialog.show();
        }
    };
    
    var _getInstance = function() {
        return (instance) ? instance : _init();
    };

    var _update = function() {
        if (modified) {
            pageWebservice.request('update', {
                params:{
                    page: JSON.stringify(currentPage)
                },
                success:function(response){
                    if (response.result) {
                        modified = false;
                        var html = bb.i18n.__('statusmanager.reload_page');
                        var dialog = popupDialog.create("confirmDialog",{
                            title: bb.i18n.__('statusmanager.content_updated'),
                            buttons:{
                                "Reload": {
                                    text: bb.i18n.__('popupmanager.button.reload'),
                                    click: function() {
                                        $(this).dialog("close");
                                        if (response.result.url) {
                                            document.location.href = bb.baseurl + response.result.url;
                                        } else {
                                            document.location.reload();
                                        }
                                    }
                                },
                                "Cancel": {
                                    text: bb.i18n.__('popupmanager.button.cancel'),
                                    click: function(a){
                                        $(this).dialog("close");
                                        return false;
                                    }
                                }
                            }
                        });
                        $(dialog.dialog).html(html);
                        dialog.show();
                    } else
                        _displayError(bb.i18n.__('statusmanager.error.updating_page'), null, _update);
                },
                error:function(response){
                    _displayError(bb.i18n.__('statusmanager.error.updating_page'), response.error, _update);
                    throw response.error;
                }
            });
        }
        
        return true;
    };
    
    var _revert = function() {
        revisionWebservice.request('getAllDrafts', {
            success: function(response) {
                if (0 == response.result.length) {
                    var alertDialog = popupDialog.create("confirmDialog",{
                        title: bb.i18n.__('statusmanager.reverting_content'),
                        buttons:{
                            "Close":{
                                text: bb.i18n.__('popupmanager.button.close'),
                                click: function(a){
                                    $(this).dialog("close");
                                    return false;
                                }
                            }
                        }
                    });
                    $(alertDialog.dialog).html(bb.i18n.__('statusmanager.none_revert_content'));
                    alertDialog.show();
                } else {
                    var confirmDialog = popupDialog.create("confirmDialog",{
                        title: bb.i18n.__('statusmanager.reverting_content'),
                        buttons:{
                            "Confirm" :{
                                text: bb.i18n.__('popupmanager.button.confirm'),
                                click: function(){
                                    $(this).parents('.ui-dialog:first').mask(bb.i18n.__('loading'));
                                    revisionWebservice.request('revert', {
                                        success: function(response) {
                                            $(this).parents('.ui-dialog:first').unmask();
                                            $(this).dialog('close');
                                            confirmDialog.destroy();
                                            
                                            var html = bb.i18n.__('statusmanager.reload_page');
                                            var dialog = popupDialog.create("confirmDialog",{
                                                title: bb.i18n.__('statusmanager.content_reverted'),
                                                buttons:{
                                                    "Reload": {
                                                        text: bb.i18n.__('popupmanager.button.reload'),
                                                        click: function() {
                                                            $(this).dialog("close");
                                                            document.location.reload();
                                                        }
                                                    },
                                                    "Cancel": {
                                                        text: bb.i18n.__('popupmanager.button.cancel'),
                                                        click: function(a){
                                                            $(this).dialog("close");
                                                            return false;
                                                        }
                                                    }
                                                }
                                            });
                                            $(dialog.dialog).html(html);
                                            dialog.show();
                                        },
                                        error:function(response){
                                            $(this).parents('.ui-dialog:first').unmask();
                                            $(this).dialog('close');
                                            confirmDialog.destroy();
                                            
                                            _displayError(bb.i18n.__('statusmanager.error.reverting'), response.error);
                                            throw response.error;
                                        }
                                    });
                                }
                            },
                            "Cancel":{
                                text: bb.i18n.__('popupmanager.button.cancel'),
                                click: function(a){
                                    $(this).dialog("close");
                                    return false;
                                }
                            }
                        }
                    });
                    $(confirmDialog.dialog).html(response.result.length + bb.i18n.__('statusmanager.content_to_revert'));
                    confirmDialog.show();
                }
            },
            error:function(response){
                _displayError(bb.i18n.__('statusmanager.error.loading_list'), response.error, _revert);
                throw response.error;
            }
        });
    };
    
    var _commit = function() {
        revisionWebservice.request('getAllDrafts', {
            success: function(response) {
                if (0 == response.result.length) {
                    var alertDialog = popupDialog.create("confirmDialog",{
                        title: bb.i18n.__('statusmanager.commiting_content'),
                        buttons:{
                            "Close":{
                                text: bb.i18n.__('popupmanager.button.close'),
                                click: function(a){
                                    $(this).dialog("close");
                                    return false;
                                }
                            }
                        }
                    });
                    $(alertDialog.dialog).html(bb.i18n.__('statusmanager.none_commit_content'));
                    alertDialog.show();
                } else {
                    var confirmDialog = popupDialog.create("confirmDialog",{
                        title: bb.i18n.__('statusmanager.commiting_content'),
                        buttons:{
                            "Confirm" :{
                                text: bb.i18n.__('popupmanager.button.confirm'),
                                click: function(){
                                    $(confirmDialog.dialogUi).mask(bb.i18n.__('loading'));
                                    revisionWebservice.request('commit', {
                                        success: function(response) {
                                            $(this).parents('.ui-dialog:first').unmask();
                                            $(this).dialog('close');
                                            confirmDialog.destroy();
                                        },
                                        error:function(response){
                                            $(this).parents('.ui-dialog:first').unmask();
                                            $(this).dialog("close");
                                            confirmDialog.destroy();
                                            
                                            if (7100 == response.error.code) {
                                                _displayError(bb.i18n.__('statusmanager.error.update_first'));
                                            } else {
                                                _displayError(bb.i18n.__('statusmanager.error.commiting'), response.error);
                                            }
                                        }
                                    });
                                }
                            },
                            "Cancel":{
                                text: bb.i18n.__('popupmanager.button.cancel'),
                                click: function(a){
                                    $(this).dialog("close");
                                    return false;
                                }
                            }
                        }
                    });
                    $(confirmDialog.dialog).html(response.result.length + bb.i18n.__('statusmanager.content_to_commit'));
                    confirmDialog.show();
                }
            },
            error:function(response){
                _displayError(bb.i18n.__('statusmanager.error.loading_list'), response.error, _revert);
                throw response.error;
            }
        });
    };
    
    var _displayError = function(msg, previous, retry) {
        var retry = retry;
        var buttons = {};
        
        if (retry) buttons.retry = {
            text: bb.i18n.__('popupmanager.button.retry'), 
            click: function() {
                $(this).dialog("close");
                return retry.call();
            }
        };
        
        buttons.close = {
            text: bb.i18n.__('popupmanager.button.close'), 
            click: function() {
                $(this).dialog("close");
            }
        };
        
        var errorDialog = popupDialog.create("alertDialog",{
            title: bb.i18n.__('statusmanager.error.error_occured'),
            buttons: buttons
        });
                
        $(errorDialog.dialog).html(msg+(previous ? ":<br/><em>"+previous.message+" ("+bb.i18n.__('statusmanager.error.code')+" "+previous.code+")</em>" : ''));
            errorDialog.show();
    };

    var _publicApi = {
        enable: _enable,
        disable: _disable,
        edit: _edit,
        remove: _remove,
        getCurrentPage : function(){
            return currentPage;
        },
        getOnline: _getOnline,
        setOnline: _setOnline,
        getHidden: _getHidden,
        setHidden: _setHidden,
        getPublishingDate: _getPublishingDate,
        setPublishingDate: _setPublishingDate,
        getArchivingDate: _getArchivingDate,
        setArchivingDate: _setArchivingDate,
        setTitle: _setTitle,
        setMetadata: _setMetadata,
        update: _update,
        commit: _commit,
        revert: _revert
    };
        
    return {
        init: _init,
        getInstance: _getInstance
    };
})(jQuery,window);