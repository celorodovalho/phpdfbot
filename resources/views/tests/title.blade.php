<table>
    <tr>
        <th>Title</th>
        <th>Position</th>
        <th>Description</th>
        <th>ExtractorHelper::extractPosition</th>
    </tr>
    @foreach($opportunities as $opportunity)
        <tr>
        <td>{{$opportunity->title}}</td>
        <td>{{$opportunity->position}}</td>
        <td>{{$opportunity->description}}</td>
        <td>@dump(\App\Helpers\ExtractorHelper::extractPosition($opportunity->title.$opportunity->description.$opportunity->position))</td>
        </tr>
    @endforeach
</table>
{{ $opportunities->links() }}
