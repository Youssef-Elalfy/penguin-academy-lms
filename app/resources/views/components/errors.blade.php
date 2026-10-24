@if($errors->any())
<div class="alert alert-danger error-summary" data-error-summary role="alert" tabindex="-1">
    <strong>Please check the following:</strong>
    <ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
</div>
@endif
