@extends('layouts.app')

@section('content')
        <h3 class="pb-3 mb-4 border-bottom mt-3">
            Oportunidades
        </h3>

        <div class="row row-cols-1 row-cols-md-3">
        @foreach($opportunities as $opportunity)
            <div class="col mb-4 col-sm-3">
                <div class="card h-100" title="{{$opportunity->created_at}}">
                    <div class="card-header">
                        <h5 class="card-title">{{($opportunity->title)}}</h5>
                    </div>
                    <div class="card-body">
                        @if($opportunity->position)
                            <h6 class="card-subtitle mb-2 text-muted">{{utf8_decode($opportunity->position)}}</h6>
                        @endif
                        @if($opportunity->description)
                            <div class="card-text">
                                @if($opportunity->location)
                                <small class="text-muted"><i class="fas fa-map-marker-alt"></i> {{$opportunity->location}}</small>
                                @endif
                            </div>
{{--                            <div class="card-text">{!! \GrahamCampbell\Markdown\Facades\Markdown::convertToHtml($opportunity->description) !!}</div>--}}
                        @elseif($opportunity->files)
                            @foreach(json_decode($opportunity->files) as $file)
                                <p><img class="img-fluid" src="{{$file}}" title="{{$opportunity->title}}" alt="{{$opportunity->title}}"/></p>
                            @endforeach
                        @endif
                        <small class="text-muted">
                            @foreach(json_decode($opportunity->tags) as $tag)
                            {{$tag}}
                            @endforeach
                        </small>
                    </div>
                    <div class="card-footer">
                        <a href="{{ route('opportunity.show', ['opportunity' => $opportunity->id]) }}" class="btn btn-primary card-link">Show</a>
                    </div>
                </div>
            </div>
        @endforeach
        </div>

        <nav class="blog-pagination">
            {{ $opportunities->links() }}
        </nav>
@endsection

