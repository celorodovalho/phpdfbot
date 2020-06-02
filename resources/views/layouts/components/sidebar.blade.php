<aside class="col-md-2 blog-sidebar">
{{--    <div class="p-3 mb-3 bg-light rounded">--}}
{{--        <h4 class="font-italic">About</h4>--}}
{{--        <p class="mb-0">Etiam porta <em>sem malesuada magna</em> mollis euismod. Cras mattis consectetur purus sit amet fermentum. Aenean lacinia bibendum nulla sed consectetur.</p>--}}
{{--    </div>--}}

    <div class="p-3">
        <h3>Tags</h3>
        <div class="mb-0">
            @foreach(array_merge(
                \Illuminate\Support\Facades\Config::get('constants.requiredWords'),
                \Illuminate\Support\Arr::flatten(\Illuminate\Support\Facades\Config::get('constants.cities')),
                \Illuminate\Support\Facades\Config::get('constants.commonWords'),
                \App\Enums\BrazilianStates::toArray(),
                \App\Enums\Countries::toArray()
            ) as $tag)
                <a href="#" class="badge badge-primary">{{$tag}}</a>
            @endforeach
        </div>
    </div>

</aside><!-- /.blog-sidebar -->
