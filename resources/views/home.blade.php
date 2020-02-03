@extends('layouts.app')

@section('content')
    <div class="col-md-8 blog-main">
        <h3 class="pb-3 mb-4 font-italic border-bottom">
            Oportunidades
        </h3>

        @foreach($opportunities as $opportunity)
        <div class="blog-post">
            <h2 class="blog-post-title">{{$opportunity->title}}</h2>
            <p class="blog-post-meta">{{$opportunity->created_at}} by <a href="#">{{$opportunity->origin}}</a></p>
            @if($opportunity->position)
                <p>{{$opportunity->position}}</p>
            @endif

            @foreach(json_decode($opportunity->files) as $file)
                <p><img class="img-fluid" src="{{$file}}" title="{{$opportunity->title}}" alt="{{$opportunity->title}}"/></p>
            @endforeach

            @if($opportunity->description)
                <p>{!! \GrahamCampbell\Markdown\Facades\Markdown::convertToHtml($opportunity->description) !!}</p>
            @endif


            @if($opportunity->salary)
                <p>{{$opportunity->salary}}</p>
            @endif
            @if($opportunity->company)
                <p>{{$opportunity->company}}</p>
            @endif
            @if($opportunity->location)
                <p>{{$opportunity->location}}</p>
            @endif
            @if($opportunity->tags)
                <p>{{$opportunity->tags}}</p>
            @endif
        </div><!-- /.blog-post -->
        @endforeach

        <nav class="blog-pagination">
            {{ $opportunities->links() }}
        </nav>

    </div><!-- /.blog-main -->
@endsection


