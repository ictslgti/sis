		<!--Block#1 start dont change the order-->
		<?php
    $title = "exam Details | SLGTI";
    // Removed session_start() since it's already in config.php
    include_once("../config.php");
    include_once("../head.php");
    include_once("../menu.php");
    ?>
		<!-- end dont change the order-->


		<!-- Block#2 start your code -->

		<!-- Heading  -->
		<div class="row">
		  <div class="col">
		    <hr>
		  </div>
		</div>

		<div class="row">
		  <div class="col text-center">
		    <h1>Students Assessments Results</h1>
		  </div>
		</div>

		<div class="row ">
		  <div class="col text-center">
		    <h3 class="display-6">Department of Information & Communication Technology</h3>
		  </div>
		</div>


		<div class="row">
		  <div class="col">
		    <hr color="black" style="height:1px;">
		  </div>
		</div>
		<br>
		<br>

		<!-- Heading end -->

		<!-- Dropdown start -->

		<div class="row text-center">

		  <div class="col">

		  </div>

		  <div class="col">
		    <div class="input-group mb-3">
		      <div class="input-group-prepend">
		        <label class="input-group-text" for="inputGroupSelect01"><i
		            class="fas fa-dice-d6"></i>&nbsp;&nbsp;Select Semister</label>
		      </div>
		      <select class="custom-select" id="inputGroupSelect01">
		        <option selected>Choose...</option>
		        <option value="1">A1</option>
		        <option value="2">A2</option>
		      </select>
		    </div>
		  </div>

		  <div class="col">
		    <div class="input-group mb-3">
		      <div class="input-group-prepend">
		        <label class="input-group-text" for="inputGroupSelect01"><i
		            class="fas fa-book-open"></i>&nbsp;&nbsp;Select Module</label>
		      </div>
		      <select class="custom-select" id="inputGroupSelect01">
		        <option selected>Choose...</option>
		        <option value="1">A1</option>
		        <option value="2">A2</option>
		      </select>
		    </div>
		  </div>

		  <div class="col text-left">
		    <button type="button" class="btn btn-outline-success rounded-pill">submit</button>
		  </div>

		</div>
		<br>
		<br>

		<div class="row">

		  <div class="col">
		  </div>

		  <div class="col">
		    <div class="container">
		      <div class="row justify-content-md-center">
		        <div class="col-md-auto">
		          <button type="button" class="btn btn-outline-primary"><i class="fas fa-clipboard-list"></i>&nbsp;&nbsp; View Assessments</button>
		        </div>
		      </div>

		    </div>
		  </div>

		  <div class="col">
		  </div>

		</div>

		<br>
		<br>

		<div class="col">
		  <table class="table text-center">
		    <thead class="thead-dark">
		      <tr>
		        <th scope="col">Assessment Name</th>
		        <th scope="col">Assessment-Date</th>
		        <th scope="col">Assessment-Type</th>
		        <th scope="col">Student Attend Assessment</th>

		      </tr>
		    </thead>
		    <tbody>
		      <tr>
		        <th scope="row"><a href="view_assessment.php"><button type="button" class="btn btn-outline-primary"><i class="fas fa-eye"></i>&nbsp;&nbsp; Assessments 1</button></a></th>
		        <td>Mark</td>
		        <td>Otto</td>
		        <td>@mdo</td>
		      </tr>

		    </tbody>
		  </table>
		</div>



		<!-- Dropdown End -->

		<!-- Assessments start -->


		<!-- end your code -->
		<!--Block#3 start dont change the order-->
		<?php include_once("../footer.php"); ?>
		<!--  end dont change the order-->