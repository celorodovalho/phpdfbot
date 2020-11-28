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
                        <h5 class="card-title">{{(ucwords($opportunity->position ?? $opportunity->title))}}</h5>
                    </div>
                    <div class="card-body">
                        @if($opportunity->title)
                            <h6 class="card-subtitle mb-2 text-muted">{{utf8_decode($opportunity->title)}}</h6>
                        @endif
                        @if(filled($opportunity->files))
                            @php($file = \Illuminate\Support\Arr::first(json_decode($opportunity->files)))
                            <p><img class="img-fluid" src="{{$file}}" title="{{$opportunity->title}}" alt="{{$opportunity->title}}"/></p>
                        @elseif(filled($opportunity->description))
                            <div class="card-text">
                                <p>{{\Illuminate\Support\Str::words(strip_tags(\GrahamCampbell\Markdown\Facades\Markdown::convertToHtml($opportunity->description)), 20, '...')}}</p>
                                @if($opportunity->location)
                                    <small class="text-muted"><i class="fas fa-map-marker-alt"></i> {{$opportunity->location}}</small>
                                @endif
                            </div>
                        @endif
                        <small class="text-muted">
                            @forelse($opportunity->tags ?? [] as $tag)
                                <a href="#" class="badge badge-primary">{{$tag}}</a>
                            @empty
                            @endforelse
                        </small>
                    </div>
                    <div class="card-footer">
                        <a href="{{ route('opportunity.show', ['opportunity' => $opportunity->id]) }}" class="btn btn-primary card-link">Visualizar</a>
                        <div class="float-right h-100"><small class="text-muted align-middle mt-2 d-block"><i class="far fa-calendar-alt"></i> {{$opportunity->created_at->format('d/m/Y H:i:s')}}</small></div>
                    </div>
                </div>
            </div>
        @endforeach
        </div>

        <nav class="blog-pagination">
            {{ $opportunities->links() }}
        </nav>
@endsection

