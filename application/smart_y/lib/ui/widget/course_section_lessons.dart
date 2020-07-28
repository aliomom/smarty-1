import 'package:flutter/material.dart';
import 'package:smarty/model/lesson/lesson.dart';

class CourseSectionLessons extends StatelessWidget {
  final String sectionName;
  final List<Lesson> lessons;
  CourseSectionLessons({@required this.lessons,@required this.sectionName})
      :assert(sectionName != null && lessons != null);
  @override
  Widget build(BuildContext context) {
    return Container(

      child: Stack(
        children: <Widget>[
          ListView.builder(
            scrollDirection: Axis.horizontal,
            itemCount: lessons.length,
            padding: EdgeInsetsDirectional.fromSTEB(15 , 50, 15, 10),
            itemBuilder: (BuildContext context, int index){
              return Card(
                color: Colors.white12,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(0.0),
                ),
                child: Container(
                  padding: EdgeInsetsDirectional.fromSTEB(10, 10, 10, 10),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                    children: <Widget>[

                          Text(lessons[index].title,
                          style: TextStyle(color: Colors.grey),
                          ),

                      Text('${index+1} of ${lessons.length}',
                        style: TextStyle(color: Colors.grey),),


                          Icon(Icons.check_circle,color: Colors.grey,),

                    ],
                  ),
                ),
              );
           },
          ),
          Positioned(
            left: 0.0,
            right: 0.0,
            top: 0.0,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: <Widget>[
                         Text(sectionName),
              ],
            ),
          ),
        ],
      ),


    );
  }
}
