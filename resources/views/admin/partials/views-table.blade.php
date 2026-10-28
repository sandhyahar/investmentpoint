{{-- Article list with view counts for the admin dashboard. $rows: [title, url, date, views] --}}
@if($rows->isEmpty())
  <p class="text-muted mb-0" style="font-size:13px">{{ $empty }}</p>
@else
  <table class="db-views">
    @foreach($rows as $i => $row)
      <tr>
        <td style="width:24px;color:#66798d">{{ $i + 1 }}</td>
        <td>
          <a href="{{ $row['url'] }}" target="_blank" rel="noopener">{{ Str::limit($row['title'], 80) }}</a>
          @if($row['date'])<br><small>{{ \Carbon\Carbon::parse($row['date'])->format('d M Y') }}</small>@endif
        </td>
        <td class="v">{{ number_format($row['views']) }} <small style="font-weight:600">{{ Str::plural('view', $row['views']) }}</small></td>
      </tr>
    @endforeach
  </table>
@endif
