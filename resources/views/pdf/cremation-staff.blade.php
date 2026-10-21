<table class="staff"><thead><tr><th class="person">Cognome e Nome</th><th>Qualifica ed estremi di abilitazione</th></tr></thead><tbody>
@foreach($r['staff'] as $member)
<tr><td><strong>{{ $member['name'] }}</strong></td><td>{{ $member['qualification'] }} — {{ $member['credentials'] }}</td></tr>
@endforeach
</tbody></table>
