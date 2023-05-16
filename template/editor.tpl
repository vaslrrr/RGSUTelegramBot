<form method="post" enctype="multipart/form-data">

    <div class="row text-center" style="margin-top: 10px; ">
        <div class="col-2"></div>
        <div class="col">
            <input name="name" class="input-form " type="text" placeholder="Название" value="%NAME%"
                   style="width: 100%;"/>
        </div>
        <div class="col-2"></div>
    </div>
    <div class="row text-center" style="margin-top: 10px;">
        <div class="col-2"></div>
        <div class="col">
                <textarea name="content" class="input-form"
                          style="width: 100%; min-height: 60%; min-height: 300px;">%CONTENT%</textarea>
        </div>
        <div class="col-2"></div>
    </div>
    <div class="row" style="margin-top: 0.7%;">
        <div class="col-2"></div>
        <div class="col text-center">
            <button type="submit" class="btn btn-login" style="width: 30%;">
                Сохранить
            </button>
        </div>
        <div class="col-2"></div>
    </div>
</form>