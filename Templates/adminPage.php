<h1>Softexpert Products Importer</h1>
<h3>Please upload a file to start the import</h3>
<p><b>Max execution time: </b><?php echo ini_get('max_execution_time'); ?></p>
<input id="sepiFile" type="file" />
<br>
<p>Delete products that are not inlcuded in the import file?</p>
<select id="sepiMode">
    <option value="delete">YES</option>
    <option value="keep">NO</option>
</select>
<br>
<button id="sepiUpload">Start</button>
<br>
<textarea id="sepiLogs" style="width:50rem;height: 50rem;"></textarea>