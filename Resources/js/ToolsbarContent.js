
/**
 * BlockContentToolsbars
 * Gère le drag and drop des contenus sur la page
 * les rendre accessible dans un tableau
 * 
 **/
BB4.ToolsbarManager.register("contenttb",{
    _settings : {
        mainContainer:"#bb5-editing",
        pathInfos :{
            pathContainerId : "#bb5-exam-path",
            pathItemClass :".contentNodeItem"
        },
        webservices : {
            contentBlocksWS: "ws_local_contentBlock"
        },
        contentBlockInfos : {
            contentCatContainerId :"#bb5-contentCatContainer",
            contentListId : "#bb5-slider-blocks"
        },
        sortableClass : ".contentBlock",
        contentBlockClass : "bb5-button bb5-button-bulky bb5-blocks-choice-x",
        connectedSortableClass : ".bb5-droppable-item",
        contentTypeDataKey : "contentTypeInfos",
        allContentKey : "Tous" 
    },
    
    _events: {
        "#bb5-contentCatContainer change" : "blockCategoryChanged",
        ".contentBlock click" : "blockClicked",
        ".contentNodeItem click" : "selectPath",
        ".bb5-onlyShowBlocksBtn click" :"toggleShowBlocks"
    },
    
    
   
    _beforeCallbacks :{
        selectPath : function(e){},
        blockClicked : function(e){
            var content = $(e.currentTarget).data(this._settings.contentTypeDataKey);
            $(this.blockInfosDialog.dialog).html($('<div class="bb5-dialog-info"><p class="bb5-dialog-visualclue-x"><i style="background-image:url('+content.contentPic+')"></i></p><div class="bb5-dialog-info-desc"><p><strong>'+content.label+'</strong></p><p><em>'+bb.i18n.__('toolbar.contents.desc')+'</em> <br>'+content.description+'</p></div>'));
            this.blockInfosDialog.show();
            this._callbacks["blockClicked_action"].call(this,content);
            return true;
        }
    },

    _init : function(){
        this.contentBlockInfos = this._settings.contentBlockInfos;
        var dialogManager = bb.PopupManager.init();
        
        this.contentBlocksWebservice = bb.webserviceManager.getInstance(this._settings.webservices.contentBlocksWS);
        this.dbFilter = new bb.Utils.FilterManager([]);
      
        this._bindPrivateEvents();
        this.contentBlockCategories = new SmartList({
            idKey:"uid", 
            onInit : $.proxy(this._onInitBlockCategories,this)
        });
       
        this.contentBlocksContainer = new SmartList({
            idKey:"uid", 
            onInit : $.proxy(this._onInitContentBlocks,this)
        });
        
        this.blockInfosDialog = dialogManager.create("blocksInfoDialog",{
            title: bb.i18n.__('contentmanager.info_title'),
            buttons:{                
                "Cancel" : {
                    text: bb.i18n.__('popupmanager.button.cancel'),
                    click: function(){
                        $(this).dialog("close");
                        return false;
                    }       
                }
            }
        });
   
    },
    
    _buildBlockInfos : function(){},
          
    /*contentType draggable*/
    _initSortable : function(){
        var self = this;
        $(this._settings.contentBlockInfos.contentListId).sortable({
            connectWith : this._settings.connectedSortableClass,
            placeholder:"bb5-droppable-place",
            items :this._settings.sortableClass,
            zIndex : 500001,
            clone: true,
            helper : function(e,el){ 
                var contentTypeParams = $(el).data(self._settings.contentTypeDataKey);
                var pictoName = bb.baseurl+'ressources/img/contents/'+contentTypeParams.name.replace('\\', '/')+'.png';
                var helper  = $("<div></div>").clone();
                $(helper).addClass("bb5-content-type-helper");
                $(helper).data(self._settings.contentTypeDataKey,contentTypeParams);
                $(helper).css({
                    backgroundImage:"url("+pictoName+")"
                });
                return helper;
            },
            
            itemdrop : function(event,ui){
                if(ui.item){
                    $(ui.item).show();
                }
            //$(this).sortable("cancel");
            },
            
            
            start:  function(event,ui){
                $(ui.item).show();
            /*$(document).trigger("content:startDrag");
                var clonedContentBlock = ui.item.clone();
                $(clonedContentBlock).addClass("prevContent");
                $(ui.item).before(clonedContentBlock);
                clonedContentBlock.show();*/
            
            },
            
            stop : function(event,ui){
                /*$(this).find(".prevContent");
                $(this).find(".prevContent").remove();*/
                $(ui.item).show();
                $(document).trigger("content:stopDrag");
            //$(this).sortable("cancel");
                
            },
            
            scroll : true,
            scrollSensitivity :200, 
            cursorAt : {
                left:-15,
                top:0
            },
            appendTo :'body',
            cancel: false
        });  
    },
    
    getContentsBlockByCat : function(catId){
        var catId = catId || false;
        var self = this;
        /*try to find in local*/
        var cleanData = this.contentBlocksContainer.toArray(true);
        this.dbFilter.setData(cleanData);
        var contentBlocks = this.dbFilter.where("category","=",catId).execute(); 
        if(contentBlocks.length){
            this._onInitContentBlocks(contentBlocks);  
        }else{
            if(this._settings.allContentKey==catId){
                this._onInitContentBlocks(cleanData);
            }
            else{
                this.contentBlocksWebservice.request("getContentBlocks",{
                    params : {
                        catId : catId,
                        withCategery : false
                    },
                    success : function(response){
                        self.contentBlocksContainer.addData(response.result.contentList);
                        self._onInitContentBlocks(response.result.contentList);
                    },
                    error : function(response){
                        throw response.error;
                    }
                });
            }
            
        }

    },
    
    
    _onInitContentBlocks : function(blocks){
        var contentFragment = document.createDocumentFragment();
        var self = this;
        
        $.each(blocks,function(i,data){
            var contentBlock = $("<li></li>").clone();
            $(contentBlock).addClass("contentBlock");
            data.contentPic = bb.baseurl+'ressources/img/contents/'+data.name.replace('\\', '/')+'.png';
            $(contentBlock).data(self._settings.contentTypeDataKey,data);
            var btn = $("<button></button>").clone();
            $(btn).append('<i style="background-image:url('+bb.baseurl+'ressources/img/contents/'+data.name.replace('\\', '/')+'.png)"></i>');
            $(btn).addClass(self._settings.contentBlockClass).attr("title",data.label);
            if(data.disable && data.disable==true){
                $(btn).addClass("bbBtnUnavailable");
            }
            $(contentBlock).append(btn);
            contentFragment.appendChild($(contentBlock).get(0));
        });
        
        if(this.blockSlide){
            //this.blockSlide.destroyShow();
            this._clearBlocksSlide();
        }
        
        $(this.contentBlockInfos.contentListId).html($(contentFragment));
        var hideAfter = false;
        if( $(this._settings.mainContainer).hasClass("ui-tabs-hide")){
            hideAfter = true;
            $(this._settings.mainContainer).removeClass("ui-tabs-hide");
        }
        $(this._settings.mainContainer).removeClass("ui-tabs-hide");
        this.blockSlide = $(this.contentBlockInfos.contentListId).bxSlider({
            nextText:'<span><i class="bxBtn visuallyhidden focusable">'+bb.i18n.__('Next')+'</i></span>',
            prevText:'<span><i class="bxBtn visuallyhidden focusable">'+bb.i18n.__('Previous')+'</i></span>',
            displaySlideQty:4,
            moveSlideQty:1,
            pager:false,
            infiniteLoop : false
        }); 
        this._initSortable();
        if(hideAfter){
            $(this._settings.mainContainer).addClass("ui-tabs-hide");
        }
        
    },
    
 
    _clearBlocksSlide : function(){      
        var tpl = $("<ul class='bbGridSlide'></ul>").clone();
        $(tpl).attr("id",this._settings.contentBlockInfos.contentListId.replace("#",""));
        $(this.contentBlockInfos.contentListId).parents(".bx-wrapper").html($(tpl));        
    },
    
    _onInitBlockCategories : function(blockCategories){
        var contentFragment = document.createDocumentFragment();
        $.each(blockCategories, function(i,blockCategory){
            var option = $("<option>"+blockCategory.name+"</option>").clone(); 
            if(blockCategory.selected==true) $(option).attr("selected","selected");
            $(option).attr("value",blockCategory.name);
            contentFragment.appendChild($(option).get(0));
        });
        
        $(this.contentBlockInfos.contentCatContainerId).html(contentFragment);
    },
    
   
    _blockCategoryChanged : function(e){
        $("#bb5-slider-blocks").mask();
        var selectCategory = e.currentTarget;
        var currentTemplate = $(selectCategory).get(0).selectedIndex;
        var items = $(selectCategory).get(0).options;
        var selectedCat = $(items[currentTemplate]).val();
        var test = this.getContentsBlockByCat(selectedCat);  
    },
    
    
    _onBlockContentLoad : function(){},
    
    _toggleShowBlocks : function(e){
        alert('ok');
    },
    
    _bindPrivateEvents : function(){
        var self = this;
        this._callbacks["blockCategoryChanged_action"] = this._blockCategoryChanged;
        this._callbacks["toggleShowBlocks_action"] = this._toggleShowBlocks
        
        $(this._settings.pathInfos.pathContainerId).delegate(this._settings.pathInfos.pathItemClass,"click",function(e){
            var nodeId = ($(e.currentTarget).attr("path-data-uid"))? $(e.currentTarget).attr("path-data-uid") : null;
            var pathInfos = $(e.currentTarget).data("nodeInfo"); 
            self._callbacks["selectPath_action"].call(self,nodeId,pathInfos);
        });
        
    },
    
    /*Construit  la liste des blocs*/
    setContentBlocks : function(contentBlocks){
        this.contentBlocksContainer.setData(contentBlocks.contentList);
        this.contentBlockCategories.setData(contentBlocks.contentCategories);
    }    

});



